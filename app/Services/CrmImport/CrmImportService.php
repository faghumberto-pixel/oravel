<?php

namespace App\Services\CrmImport;

use App\Models\CrmConta;
use App\Models\CrmContaContato;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\CrmOrigem;
use App\Models\CrmPipeline;
use App\Models\CrmProduto;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\CpfCnpj;
use App\Services\CrmFunilPadrao;
use App\Support\CrmCamposCustom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Importa leads, contas, contatos e produtos/serviços de uma planilha já lida (CrmImportLeitor)
 * com o mapeamento escolhido na tela. Cada linha roda num savepoint: erro numa
 * linha não derruba as outras. `$simular` faz tudo e desfaz no fim.
 *
 * Opções: se_existir = ignorar | completar | sobrescrever; pai = conta | lead (contatos).
 */
class CrmImportService
{
    private Tenant $tenant;

    private string $destino;

    private array $opcoes;

    private array $campos;

    /** @var array<string, string> chave de duplicidade => 1ª linha que a usou no arquivo */
    private array $vistos = [];

    private array $avisosLinha = [];

    /**
     * @param  array<int, array<int, string>>  $linhas  número da linha => valores por coluna
     * @param  array<string, int|string|null>  $mapeamento  campo => índice da coluna
     */
    public function processar(string $destino, array $linhas, array $mapeamento, array $opcoes, Tenant $tenant, bool $simular = false): RelatorioImportacao
    {
        $this->tenant = $tenant;
        $this->destino = $destino;
        $this->opcoes = $opcoes + ['se_existir' => 'ignorar', 'pai' => 'conta'];
        $this->campos = CrmImportDestinos::campos($destino, (string) $tenant->id);
        $this->vistos = [];

        $relatorio = new RelatorioImportacao($simular);
        $indices = $this->carregarIndices();

        DB::beginTransaction();

        try {
            foreach ($linhas as $numero => $celulas) {
                $dados = $this->extrairBruto($celulas, $mapeamento);

                if ($dados === []) {
                    continue;
                }

                $this->avisosLinha = [];
                DB::beginTransaction();

                try {
                    [$acao, $nome, $mensagem] = match ($destino) {
                        CrmImportDestinos::LEAD => $this->linhaLead($numero, $dados, $indices),
                        CrmImportDestinos::CONTA => $this->linhaConta($numero, $dados, $indices),
                        CrmImportDestinos::PRODUTO => $this->linhaProduto($numero, $dados),
                        default => $this->linhaContato($numero, $dados, $indices),
                    };

                    $acao === RelatorioImportacao::IGNORADO ? DB::rollBack() : DB::commit();
                    $mensagem = trim($mensagem.' '.($this->avisosLinha ? 'Avisos: '.implode(' ', $this->avisosLinha) : ''));
                    $relatorio->adicionar($numero, $acao, $nome, $mensagem, count($this->avisosLinha));
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $mensagem = $e instanceof \DomainException ? $e->getMessage() : 'Erro inesperado: '.$e->getMessage();
                    $relatorio->adicionar($numero, RelatorioImportacao::ERRO, (string) ($dados['name'] ?? $dados['company_name'] ?? $dados['nome'] ?? ''), $mensagem);
                }
            }

            $simular ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $relatorio;
    }

    // ---------------------------------------------------------------- extração

    /** Valores da linha só dos campos mapeados e não vazios (ainda como texto). */
    private function extrairBruto(array $celulas, array $mapeamento): array
    {
        $dados = [];

        foreach ($mapeamento as $campo => $indice) {
            if ($indice === null || $indice === '' || ! isset($this->campos[$campo])) {
                continue;
            }

            $valor = trim((string) ($celulas[(int) $indice] ?? ''));

            if ($valor !== '') {
                $dados[$campo] = $valor;
            }
        }

        return $dados;
    }

    /** Converte e valida um valor pelo tipo do campo. Lança DomainException se inválido. */
    private function converter(string $campo, string $valor): mixed
    {
        $def = $this->campos[$campo];
        $rotulo = $def['rotulo'];

        switch ($def['tipo']) {
            case 'email':
                if (! filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    throw new \DomainException("{$rotulo}: \"{$valor}\" não é um e-mail válido.");
                }

                return mb_strtolower($valor);
            case 'uf':
                $uf = mb_strtoupper($valor);
                if (! preg_match('/^[A-Z]{2}$/', $uf)) {
                    throw new \DomainException("{$rotulo}: use a sigla de 2 letras (veio \"{$valor}\").");
                }

                return $uf;
            case 'doc':
                if (! Validator::make(['d' => $valor], ['d' => [new CpfCnpj]])->passes()) {
                    throw new \DomainException("{$rotulo}: \"{$valor}\" não é um CPF/CNPJ válido.");
                }

                return $valor;
            case 'dinheiro':
                $n = $this->numero($valor);
                if ($n === null) {
                    throw new \DomainException("{$rotulo}: \"{$valor}\" não é um valor numérico.");
                }

                return $n;
            case 'booleano':
                return in_array(CrmImportDestinos::normalizar($valor), ['sim', 's', '1', 'true', 'verdadeiro', 'x'], true);
            default:
                return $valor;
        }
    }

    private function numero(string $valor): ?float
    {
        $v = preg_replace('/[^\d,.\-]/', '', str_replace('R$', '', $valor));

        if ($v === '' || $v === '-') {
            return null;
        }

        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace(',', '.', str_replace('.', '', $v));
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private function data(string $valor): ?string
    {
        try {
            return preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $valor)
                ? Carbon::createFromFormat('d/m/Y', $valor)->toDateString()
                : Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Campos personalizados: converte pelo tipo do campo (BR → formato interno) e deixa o modelo normalizar. */
    private function extrairCustom(string $entidade, array $dados): array
    {
        $definicoes = CrmCamposCustom::campos($entidade, true, (string) $this->tenant->id)->keyBy('chave');
        $custom = [];

        foreach ($dados as $campo => $valor) {
            if (! str_starts_with($campo, 'custom.')) {
                continue;
            }

            $def = $definicoes->get(substr($campo, 7));

            if (! $def) {
                continue;
            }

            $custom[$def->chave] = match ($def->tipo) {
                'numero', 'moeda' => $this->numero($valor),
                'data' => $this->data($valor),
                'sim_nao' => in_array(CrmImportDestinos::normalizar($valor), ['sim', 's', '1', 'true', 'verdadeiro'], true),
                'lista' => $this->opcaoDaLista($def, $valor),
                'lista_multipla' => array_values(array_filter(array_map(fn ($v) => $this->opcaoDaLista($def, $v), preg_split('/[;,|]/', $valor)))),
                default => $valor,
            };

            if ($custom[$def->chave] === null || $custom[$def->chave] === []) {
                unset($custom[$def->chave]);
                $this->avisosLinha[] = "{$def->nome}: valor \"{$valor}\" não encaixa no campo e foi ignorado.";
            }
        }

        return $custom;
    }

    private function opcaoDaLista($def, string $valor): ?string
    {
        $alvo = CrmImportDestinos::normalizar($valor);

        foreach ($def->opcoesLabels() as $opcao) {
            if (CrmImportDestinos::normalizar($opcao) === $alvo) {
                return $opcao;
            }
        }

        return null;
    }

    private function digitos(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    // ---------------------------------------------------------------- índices

    private function carregarIndices(): array
    {
        $t = $this->tenant->id;

        $indices = ['leadPorDoc' => [], 'leadPorEmail' => [], 'contaPorDoc' => [], 'contaPorNome' => [], 'etapas' => [], 'origens' => [], 'usuarios' => []];

        if ($this->destino === CrmImportDestinos::LEAD) {
            // Garante funil padrão, origens e motivos do tenant (idempotente).
            CrmFunilPadrao::garantir((string) $t);

            foreach (CrmLead::withoutGlobalScopes()->where('tenant_id', $t)->get(['id', 'document', 'email']) as $l) {
                if ($this->digitos($l->document) !== '') {
                    $indices['leadPorDoc'][$this->digitos($l->document)] = $l->id;
                }
                if (filled($l->email)) {
                    $indices['leadPorEmail'][mb_strtolower($l->email)] = $l->id;
                }
            }

            $funil = CrmPipeline::withoutGlobalScopes()->where('tenant_id', $t)->where('padrao', true)->value('id');
            foreach (CrmEtapa::withoutGlobalScopes()->where('tenant_id', $t)->where('crm_funil_id', $funil)->get() as $e) {
                $indices['etapas'][CrmImportDestinos::normalizar($e->nome)] = $e->id;
            }
            foreach (CrmOrigem::withoutGlobalScopes()->where('tenant_id', $t)->get() as $o) {
                $indices['origens'][CrmImportDestinos::normalizar($o->nome)] = $o->slug;
            }
        }

        if (in_array($this->destino, [CrmImportDestinos::LEAD, CrmImportDestinos::CONTA], true)) {
            foreach (User::withoutGlobalScopes()->where('tenant_id', $t)->get(['id', 'email']) as $u) {
                $indices['usuarios'][mb_strtolower($u->email)] = $u->id;
            }
        }

        // Contas: dedup da conta e checagem "lead que já é cliente".
        if (in_array($this->destino, [CrmImportDestinos::LEAD, CrmImportDestinos::CONTA], true)) {
            foreach (CrmConta::withoutGlobalScopes()->where('tenant_id', $t)->get(['id', 'nome', 'documento_digitos']) as $c) {
                if ($c->documento_digitos) {
                    $indices['contaPorDoc'][$c->documento_digitos] = $c->id;
                }
                $indices['contaPorNome'][CrmImportDestinos::normalizar($c->nome)] = $c->id;
            }
        }

        return $indices;
    }

    private function marcarVisto(string $chave, int $linha): void
    {
        if (isset($this->vistos[$chave])) {
            throw new RepetidoNoArquivo("Repetido na planilha (primeira vez na linha {$this->vistos[$chave]}).");
        }

        $this->vistos[$chave] = $linha;
    }

    // ---------------------------------------------------------------- lead

    private function linhaLead(int $linha, array $bruto, array &$ix): array
    {
        $dados = [];
        foreach ($bruto as $campo => $valor) {
            if (in_array($campo, ['origem', 'etapa', 'responsavel', 'segment'], true) || str_starts_with($campo, 'custom.')) {
                continue;
            }
            $dados[$campo] = $this->converter($campo, $valor);
        }

        $nome = $dados['name'] ?? $dados['company_name'] ?? null;
        if (! $nome) {
            throw new \DomainException('Informe o nome do contato ou a empresa.');
        }
        $dados['name'] = $nome;
        $rotuloLinha = (string) ($dados['company_name'] ?? $nome);

        $doc = $this->digitos($dados['document'] ?? null);
        $email = isset($dados['email']) ? mb_strtolower($dados['email']) : null;
        $chave = $doc !== '' ? 'd'.$doc : ($email ? 'e'.$email : null);

        try {
            if ($chave) {
                $this->marcarVisto($chave, $linha);
            }
        } catch (RepetidoNoArquivo $e) {
            return [RelatorioImportacao::IGNORADO, $rotuloLinha, $e->getMessage()];
        }

        if ($doc !== '' && isset($ix['contaPorDoc'][$doc])) {
            return [RelatorioImportacao::IGNORADO, $rotuloLinha, 'Já é cliente (mesmo CNPJ/CPF).'];
        }

        // Campos que exigem tradução (podem gerar aviso, nunca erro).
        if (isset($bruto['segment'])) {
            $slug = CrmImportDestinos::segmentos()[$bruto['segment']] ?? collect(CrmImportDestinos::segmentos())->first(fn ($s, $r) => CrmImportDestinos::normalizar($r) === CrmImportDestinos::normalizar($bruto['segment']));
            $slug ? $dados['segment'] = $slug : $this->avisosLinha[] = "Segmento \"{$bruto['segment']}\" não reconhecido.";
        }
        if (isset($bruto['origem'])) {
            $slug = $ix['origens'][CrmImportDestinos::normalizar($bruto['origem'])] ?? null;
            $slug ? $dados['source'] = $slug : $this->avisosLinha[] = "Origem \"{$bruto['origem']}\" não existe; ficou \"Outro\".";
            $dados['source'] ??= 'outro';
        }
        if (isset($bruto['responsavel'])) {
            $uid = $ix['usuarios'][mb_strtolower($bruto['responsavel'])] ?? null;
            $uid ? $dados['assigned_user_id'] = $uid : $this->avisosLinha[] = "Responsável \"{$bruto['responsavel']}\" não encontrado; ficou sem responsável.";
        }
        $custom = $this->extrairCustom('lead', $bruto);

        $existenteId = ($doc !== '' ? ($ix['leadPorDoc'][$doc] ?? null) : null) ?? ($email ? ($ix['leadPorEmail'][$email] ?? null) : null);

        if ($existenteId) {
            $existente = CrmLead::withoutGlobalScopes()->findOrFail($existenteId);

            return $this->aplicarExistente($existente, $dados, $custom, $rotuloLinha);
        }

        $dados['tenant_id'] = $this->tenant->id;
        if (isset($bruto['etapa'])) {
            $etapaId = $ix['etapas'][CrmImportDestinos::normalizar($bruto['etapa'])] ?? null;
            $etapaId ? $dados['etapa_id'] = $etapaId : $this->avisosLinha[] = "Etapa \"{$bruto['etapa']}\" não existe no funil padrão; entrou na primeira etapa.";
        }
        if ($custom) {
            $dados['custom'] = $custom;
        }

        $lead = CrmLead::create($dados);
        $doc !== '' && $ix['leadPorDoc'][$doc] = $lead->id;
        $email && $ix['leadPorEmail'][$email] = $lead->id;

        return [RelatorioImportacao::CRIADO, $rotuloLinha, ''];
    }

    // ---------------------------------------------------------------- conta

    private function linhaConta(int $linha, array $bruto, array &$ix): array
    {
        $dados = [];
        foreach ($bruto as $campo => $valor) {
            if ($campo === 'responsavel' || str_starts_with($campo, 'custom.')) {
                continue;
            }
            $dados[$campo] = $this->converter($campo, $valor);
        }

        if (blank($dados['nome'] ?? null)) {
            throw new \DomainException('Nome/razão social é obrigatório.');
        }

        $nome = (string) $dados['nome'];
        $doc = $this->digitos($dados['documento'] ?? null);
        $chave = $doc !== '' ? 'd'.$doc : 'n'.CrmImportDestinos::normalizar($nome);

        try {
            $this->marcarVisto($chave, $linha);
        } catch (RepetidoNoArquivo $e) {
            return [RelatorioImportacao::IGNORADO, $nome, $e->getMessage()];
        }

        if (isset($bruto['responsavel'])) {
            $uid = $ix['usuarios'][mb_strtolower($bruto['responsavel'])] ?? null;
            $uid ? $dados['responsavel_user_id'] = $uid : $this->avisosLinha[] = "Responsável \"{$bruto['responsavel']}\" não encontrado; ficou sem responsável.";
        }
        $custom = $this->extrairCustom('conta', $bruto);

        $existenteId = $doc !== '' ? ($ix['contaPorDoc'][$doc] ?? null) : ($ix['contaPorNome'][CrmImportDestinos::normalizar($nome)] ?? null);

        if ($existenteId) {
            return $this->aplicarExistente(CrmConta::withoutGlobalScopes()->findOrFail($existenteId), $dados, $custom, $nome);
        }

        $dados['tenant_id'] = $this->tenant->id;
        if ($custom) {
            $dados['custom'] = $custom;
        }

        $conta = CrmConta::create($dados);
        $doc !== '' && $ix['contaPorDoc'][$doc] = $conta->id;
        $ix['contaPorNome'][CrmImportDestinos::normalizar($nome)] = $conta->id;

        return [RelatorioImportacao::CRIADO, $nome, ''];
    }

    // ---------------------------------------------------------------- produto

    private function linhaProduto(int $linha, array $bruto): array
    {
        $nome = trim((string) ($bruto['nome'] ?? ''));

        if ($nome === '') {
            throw new \DomainException('Nome é obrigatório.');
        }

        $dados = ['nome' => $nome];

        foreach (['codigo', 'descricao'] as $campo) {
            if (isset($bruto[$campo])) {
                $dados[$campo] = $bruto[$campo];
            }
        }

        if (isset($bruto['preco'])) {
            $preco = $this->numero($bruto['preco']);
            if ($preco === null || $preco < 0) {
                throw new \DomainException("Preço: \"{$bruto['preco']}\" não é um valor válido.");
            }
            $dados['preco'] = $preco;
        }

        if (isset($bruto['tipo'])) {
            $alvo = CrmImportDestinos::normalizar($bruto['tipo']);
            $tipo = collect(CrmProduto::tipoLabels())->search(fn ($rotulo, $slug) => in_array($alvo, [CrmImportDestinos::normalizar($rotulo), $slug], true));
            $tipo !== false ? $dados['tipo'] = $tipo : $this->avisosLinha[] = "Tipo \"{$bruto['tipo']}\" não reconhecido; ficou como Serviço.";
        }

        if (isset($bruto['unidade'])) {
            $alvo = CrmImportDestinos::normalizar($bruto['unidade']);
            $un = collect(CrmProduto::unidadeLabels())->search(fn ($rotulo, $slug) => in_array($alvo, [CrmImportDestinos::normalizar($rotulo), $slug], true));
            $un !== false ? $dados['unidade'] = $un : $this->avisosLinha[] = "Unidade \"{$bruto['unidade']}\" não reconhecida; ficou como Unidade.";
        }

        $chave = isset($dados['codigo']) ? 'c'.mb_strtolower($dados['codigo']) : 'n'.CrmImportDestinos::normalizar($nome);

        try {
            $this->marcarVisto($chave, $linha);
        } catch (RepetidoNoArquivo $e) {
            return [RelatorioImportacao::IGNORADO, $nome, $e->getMessage()];
        }

        $existente = CrmProduto::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get()
            ->first(fn ($p) => isset($dados['codigo'])
                ? mb_strtolower((string) $p->codigo) === mb_strtolower($dados['codigo'])
                : CrmImportDestinos::normalizar($p->nome) === CrmImportDestinos::normalizar($nome));

        if ($existente) {
            return $this->aplicarExistente($existente, $dados, [], $nome);
        }

        CrmProduto::create($dados + ['tenant_id' => $this->tenant->id]);

        return [RelatorioImportacao::CRIADO, $nome, ''];
    }

    // ---------------------------------------------------------------- contato

    private function linhaContato(int $linha, array $bruto, array &$ix): array
    {
        $dados = [];
        foreach ($bruto as $campo => $valor) {
            if (in_array($campo, ['pai_documento', 'pai_nome'], true)) {
                continue;
            }
            $dados[$campo] = $this->converter($campo, $valor);
        }

        if (blank($dados['name'] ?? null)) {
            throw new \DomainException('Nome do contato é obrigatório.');
        }

        $nome = (string) $dados['name'];
        $paiTipo = $this->opcoes['pai'] === 'lead' ? 'lead' : 'conta';
        $pai = $this->acharPai($paiTipo, $bruto);

        $chaveContato = isset($dados['email']) ? 'e'.mb_strtolower($dados['email']) : 'n'.CrmImportDestinos::normalizar($nome);

        try {
            $this->marcarVisto($paiTipo.$pai->id.'|'.$chaveContato, $linha);
        } catch (RepetidoNoArquivo $e) {
            return [RelatorioImportacao::IGNORADO, $nome, $e->getMessage()];
        }

        $modelo = $paiTipo === 'lead' ? CrmLeadContact::class : CrmContaContato::class;
        $fk = $paiTipo === 'lead' ? 'crm_lead_id' : 'crm_conta_id';

        $existente = $modelo::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where($fk, $pai->id)
            ->get()->first(fn ($c) => isset($dados['email'])
                ? mb_strtolower((string) $c->email) === mb_strtolower($dados['email'])
                : CrmImportDestinos::normalizar($c->name) === CrmImportDestinos::normalizar($nome));

        if ($existente) {
            return $this->aplicarExistente($existente, $dados, [], $nome);
        }

        $modelo::create($dados + ['tenant_id' => $this->tenant->id, $fk => $pai->id]);

        return [RelatorioImportacao::CRIADO, $nome, ''];
    }

    private function acharPai(string $tipo, array $bruto): CrmConta|CrmLead
    {
        $doc = $this->digitos($bruto['pai_documento'] ?? null);
        $nome = CrmImportDestinos::normalizar($bruto['pai_nome'] ?? null);

        if ($doc === '' && $nome === '') {
            throw new \DomainException('Informe o CNPJ/CPF ou o nome do '.$tipo.' a que o contato pertence.');
        }

        $modelo = $tipo === 'lead' ? CrmLead::class : CrmConta::class;
        $candidatos = $modelo::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get();

        $achado = $candidatos->first(function ($r) use ($doc, $nome, $tipo) {
            if ($doc !== '') {
                return $this->digitos($tipo === 'lead' ? $r->document : $r->documento) === $doc;
            }

            return $tipo === 'lead'
                ? in_array($nome, [CrmImportDestinos::normalizar($r->name), CrmImportDestinos::normalizar($r->company_name)], true)
                : in_array($nome, [CrmImportDestinos::normalizar($r->nome), CrmImportDestinos::normalizar($r->nome_fantasia)], true);
        });

        if (! $achado) {
            throw new \DomainException("{$tipo} \"".($bruto['pai_nome'] ?? $bruto['pai_documento']).'" não encontrado. Cadastre ou importe-o antes.');
        }

        return $achado;
    }

    // ---------------------------------------------------------------- atualização

    /** Aplica a opção "se já existir" a um registro existente. */
    private function aplicarExistente($existente, array $dados, array $custom, string $nome): array
    {
        $modo = $this->opcoes['se_existir'];

        if ($modo === 'ignorar') {
            return [RelatorioImportacao::IGNORADO, $nome, 'Já existe (mesmo CNPJ/CPF, e-mail ou nome).'];
        }

        $mudancas = [];

        foreach ($dados as $campo => $valor) {
            if ($valor === null || $valor === '' || in_array($campo, ['tenant_id', 'etapa_id'], true)) {
                continue;
            }

            if ($modo === 'sobrescrever' || blank($existente->{$campo})) {
                $mudancas[$campo] = $valor;
            }
        }

        if ($custom) {
            $atual = (array) $existente->custom;
            $novo = $modo === 'sobrescrever' ? array_merge($atual, $custom) : array_merge($custom, $atual);
            $mudancas['custom'] = $novo;
        }

        if (! $mudancas) {
            return [RelatorioImportacao::IGNORADO, $nome, 'Já existe e não há nada novo para preencher.'];
        }

        $existente->fill($mudancas)->save();

        return [RelatorioImportacao::ATUALIZADO, $nome, ''];
    }
}
