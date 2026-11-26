<?php

namespace App\Domain\Intelligence\Suprimentos;

use App\Domain\Intelligence\Services\DiagnosticoCalculator;
use App\Domain\Intelligence\Suprimentos\Models\SuprimentosCarga;
use App\Domain\Intelligence\Suprimentos\Models\SuprimentosCompra;
use App\Domain\Intelligence\Suprimentos\Models\SuprimentosItem;
use App\Domain\Intelligence\Suprimentos\Models\SuprimentosMovimento;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AssetImport\AssetImportColumns as Norm;
use App\Support\ExcelImport\XlsxReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa a planilha de SUPRIMENTOS (modelo próprio, ver ModeloSuprimentos): valida aba por aba, calcula indicadores e nota e grava
 * uma carga (fotografia) com as linhas. Só o Inventário é obrigatório; Compras e Movimentações abrem mais indicadores.
 */
class ImportadorSuprimentos
{
    public function __construct(private IndicadoresDeSuprimentos $calculo = new IndicadoresDeSuprimentos, private DiagnosticoCalculator $diagnostico = new DiagnosticoCalculator) {}

    /**
     * @return array{ok: bool, simulacao: bool, erros: list<string>, avisos: list<string>, carga: ?SuprimentosCarga, indicadores: array, diagnostico: ?array, totais: array<string, int>}
     */
    public function importar(string $caminho, Tenant $tenant, ?User $usuario = null, bool $simular = false, ?string $nomeArquivo = null, string $origem = 'arquivo'): array
    {
        $r = ['ok' => false, 'simulacao' => $simular, 'erros' => [], 'avisos' => [], 'carga' => null, 'indicadores' => [], 'diagnostico' => null, 'totais' => ['itens' => 0, 'compras' => 0, 'movimentos' => 0]];
        $max = (int) config('intelligence.suprimentos.max_linhas');

        try {
            [$dados, $erros] = $this->lerDados($caminho);
            $inv = $this->lerAba($caminho, Catalogo::ABA_INVENTARIO, Catalogo::inventario(), 'codigo', true, $max);
            $com = $this->lerAba($caminho, Catalogo::ABA_COMPRAS, Catalogo::compras(), 'codigo_item', false, $max);
            $mov = $this->lerAba($caminho, Catalogo::ABA_MOVIMENTOS, Catalogo::movimentos(), 'codigo_item', false, $max);
        } catch (Throwable $e) {
            $r['erros'][] = 'Não consegui ler a planilha. Confira se é um arquivo .xlsx do modelo de Suprimentos.';
            report($e);

            return $r;
        }
        $r['erros'] = array_merge($erros, $inv['erros'], $com['erros'], $mov['erros']);
        if ($r['erros'] !== []) {
            return $r;
        }

        $itens = $this->normalizarItens($inv['linhas'], $r['avisos']);
        $compras = array_map(fn ($c) => $c + ['emergencial' => (bool) ($c['emergencial'] ?? false)], $com['linhas']);
        $movs = array_map(fn ($m) => ['tipo' => Str::lower(Norm::normalize((string) $m['tipo'])) === 'entrada' ? 'entrada' : 'saida'] + $m, $mov['linhas']);

        $desconhecidos = array_unique(array_merge(
            array_diff(array_column($compras, 'codigo_item'), array_column($itens, 'codigo')),
            array_diff(array_column($movs, 'codigo_item'), array_column($itens, 'codigo')),
        ));
        if ($desconhecidos !== []) {
            $r['avisos'][] = count($desconhecidos).' código(s) de Compras/Movimentações não existem no Inventário e ficam fora dos valores (ex.: '.implode(', ', array_slice($desconhecidos, 0, 5)).').';
        }

        $ref = Carbon::parse($dados['data_referencia']);
        $periodo = (int) ($dados['periodo_dias'] ?? config('intelligence.suprimentos.periodo_padrao_dias'));
        $calc = $this->calculo->calcular($itens, $compras, $movs, $ref, $periodo);
        $r['avisos'] = array_merge($r['avisos'], $calc['avisos']);
        $r['totais'] = ['itens' => count($itens), 'compras' => count($compras), 'movimentos' => count($movs)];

        if (! collect($calc['indicadores'])->contains(fn ($i) => $i['ruim'] !== null)) {
            $r['erros'][] = 'Os dados preenchidos não permitem calcular nenhum indicador de Suprimentos. Preencha o Inventário (com estoque mínimo e datas de saída) e, de preferência, Compras e Movimentações.';

            return $r;
        }

        $diag = $this->diagnostico->calcular($calc['indicadores']);
        $area = $diag[Catalogo::AREA] ?? null;
        foreach ($calc['indicadores'] as $chave => &$i) {
            $i['nota'] = $area['detalhes'][$chave] ?? null;
        }
        unset($i);
        $r['indicadores'] = $calc['indicadores'];
        $r['diagnostico'] = $area;

        $hash = hash('sha256', json_encode([$dados, $itens, $compras, $movs], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $igual = SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('hash', $hash)->first();
        if ($igual) {
            $r['erros'][] = 'Esta planilha é idêntica à importada em '.$igual->created_at?->format('d/m/Y H:i').' (referência '.$igual->data_referencia->format('d/m/Y').'). Nada foi gravado.';

            return $r;
        }
        if ($simular) {
            $r['ok'] = true;

            return $r;
        }
        if ($origem !== ExemploDeSuprimentos::ORIGEM && ($bloqueio = (new TrialCiclo)->motivoDoBloqueio($tenant))) {
            $r['erros'][] = $bloqueio;

            return $r;
        }

        try {
            DB::transaction(function () use ($tenant, $usuario, $nomeArquivo, $origem, $dados, $periodo, $hash, $itens, $compras, $movs, $calc, $area, &$r) {
                $carga = SuprimentosCarga::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id, 'user_id' => $usuario?->id, 'origem' => $origem, 'arquivo' => $nomeArquivo, 'data_referencia' => $dados['data_referencia'],
                    'periodo_dias' => $periodo, 'hash' => $hash, 'total_itens' => count($itens), 'total_compras' => count($compras), 'total_movimentos' => count($movs),
                    'indicadores' => $r['indicadores'], 'diagnostico' => $area, 'listas' => $calc['listas'], 'avisos' => $r['avisos'],
                ]);
                $this->gravar(SuprimentosItem::class, $tenant->id, $carga->id, $itens);
                $this->gravar(SuprimentosCompra::class, $tenant->id, $carga->id, $compras);
                $this->gravar(SuprimentosMovimento::class, $tenant->id, $carga->id, $movs);
                $r['carga'] = $carga;
            });
            $r['ok'] = true;
        } catch (Throwable $e) {
            report($e);
            $r['erros'][] = 'Falha ao gravar a importação: '.$e->getMessage();
        }

        return $r;
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function lerDados(string $caminho): array
    {
        $rows = XlsxReader::rowsDaAba($caminho, Catalogo::ABA_DADOS);
        if ($rows === null) {
            return [[], ['Aba "Dados" não encontrada. Use o modelo de Suprimentos da Oravel (é diferente do modelo geral do Intelligence).']];
        }
        $campos = Catalogo::dados();
        [$mapa, $primeira] = $this->cabecalho($rows, $campos, 'data_referencia');
        if ($mapa === null) {
            return [[], ['Aba "Dados": cabeçalho não encontrado (coluna "Data de Referência").']];
        }
        $linha = null;
        foreach ($rows as $n => $celulas) {
            if ($n > $primeira && ! $this->vazia($celulas)) {
                $linha = $celulas;
                break;
            }
        }
        if ($linha === null) {
            return [[], ['Aba "Dados": preencha a Data de Referência abaixo do cabeçalho.']];
        }
        $out = [];
        $erros = [];
        foreach ($mapa as $i => $campo) {
            try {
                $v = $this->converter($campo, $linha[$i] ?? null);
            } catch (\DomainException $e) {
                $erros[] = 'Aba "Dados", '.$campo['title'].': '.$e->getMessage();

                continue;
            }
            if ($v === null && $campo['required']) {
                $erros[] = 'Aba "Dados", '.$campo['title'].': obrigatório.';
            }
            $out[$campo['key']] = $v;
        }
        if (isset($out['periodo_dias']) && ($out['periodo_dias'] < 30 || $out['periodo_dias'] > 365)) {
            $erros[] = 'Aba "Dados", Período de Análise: use um valor entre 30 e 365 dias.';
        }

        return [$out, $erros];
    }

    /**
     * Lê uma aba de linhas. Aba ausente: obrigatória = erro; opcional = sem linhas.
     *
     * @return array{linhas: list<array<string, mixed>>, erros: list<string>}
     */
    private function lerAba(string $caminho, string $aba, array $campos, string $chave, bool $obrigatoria, int $max): array
    {
        $rows = XlsxReader::rowsDaAba($caminho, $aba);
        if ($rows === null) {
            return ['linhas' => [], 'erros' => $obrigatoria ? ['Aba "'.$aba.'" não encontrada. Use o modelo de Suprimentos da Oravel.'] : []];
        }
        [$mapa, $primeira] = $this->cabecalho($rows, $campos, $chave);
        if ($mapa === null) {
            return ['linhas' => [], 'erros' => ['Aba "'.$aba.'": cabeçalho não encontrado. Use o modelo da Oravel.']];
        }

        $dados = [];
        foreach ($rows as $n => $celulas) {
            if ($n <= $primeira || $this->vazia($celulas)) {
                continue;
            }
            $primeiroCampo = Norm::normalize((string) ($celulas[array_key_first($mapa)] ?? ''));
            if (str_starts_with($primeiroCampo, '(exemplo')) {
                continue;
            }
            $dados[$n] = $celulas;
        }
        if (count($dados) > $max) {
            return ['linhas' => [], 'erros' => ['Aba "'.$aba.'": '.count($dados).' linhas e o limite é de '.$max.' por envio.']];
        }
        if ($dados === [] && $obrigatoria) {
            return ['linhas' => [], 'erros' => ['A aba "'.$aba.'" não tem nenhuma linha de dados.']];
        }

        $linhas = [];
        $erros = [];
        $vistos = [];
        foreach ($dados as $n => $celulas) {
            $l = [];
            $errosLinha = [];
            foreach ($mapa as $i => $campo) {
                try {
                    $v = $this->converter($campo, $celulas[$i] ?? null);
                } catch (\DomainException $e) {
                    $errosLinha[] = $campo['title'].': '.$e->getMessage();

                    continue;
                }
                if ($v === null && $campo['required']) {
                    $errosLinha[] = $campo['title'].': obrigatório.';
                }
                $l[$campo['key']] = $v;
            }
            if ($aba === Catalogo::ABA_INVENTARIO && isset($l['codigo'])) {
                $l['codigo'] = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $l['codigo'])));
                if (isset($vistos[$l['codigo']])) {
                    $errosLinha[] = 'Código do Item repetido (já na linha '.$vistos[$l['codigo']].').';
                }
                $vistos[$l['codigo']] ??= $n;
            }
            if ($aba !== Catalogo::ABA_INVENTARIO && isset($l['codigo_item'])) {
                $l['codigo_item'] = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $l['codigo_item'])));
            }
            if ($errosLinha !== []) {
                $erros[] = 'Aba "'.$aba.'", linha '.$n.': '.implode(' ', $errosLinha);
            }
            $linhas[] = $l;
        }
        if (count($erros) > 20) {
            $resto = count($erros) - 20;
            $erros = array_slice($erros, 0, 20);
            $erros[] = "… e mais {$resto} linha(s) com erro na aba \"{$aba}\".";
        }

        return ['linhas' => $linhas, 'erros' => $erros];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: ?array<int, array<string, mixed>>, 1: int} [coluna => campo, linha do cabeçalho]
     */
    private function cabecalho(array $rows, array $campos, string $chaveObrigatoria): array
    {
        $porTitulo = [];
        foreach ($campos as $c) {
            $porTitulo[Norm::normalize($c['title'])] = $c;
        }
        foreach ($rows as $n => $celulas) {
            $achados = [];
            foreach ($celulas as $i => $texto) {
                $t = Norm::normalize(trim(preg_replace('/\s*\*\s*$/', '', (string) $texto)));
                if (isset($porTitulo[$t])) {
                    $achados[$i] = $porTitulo[$t];
                }
            }
            if (collect($achados)->contains(fn ($c) => $c['key'] === $chaveObrigatoria)) {
                return [$achados, $n];
            }
        }

        return [null, 0];
    }

    private function vazia(array $celulas): bool
    {
        return collect($celulas)->every(fn ($v) => trim((string) ($v instanceof \DateTimeInterface ? $v->format('Y') : $v)) === '');
    }

    /** @param  list<string>  $avisos */
    private function normalizarItens(array $linhas, array &$avisos): array
    {
        $padrao = (string) config('intelligence.suprimentos.categoria_padrao');
        $fora = [];
        foreach ($linhas as &$l) {
            $l['categoria'] ??= $padrao;
            $l['quantidade'] = (float) $l['quantidade'];
            $l['estoque_minimo'] = isset($l['estoque_minimo']) ? (float) $l['estoque_minimo'] : null;
            $l['custo_unitario'] = (float) $l['custo_unitario'];
        }
        unset($l);

        return $linhas;
    }

    private function converter(array $campo, mixed $bruto): mixed
    {
        $tipo = $campo['type'];
        if ($tipo === 'date') {
            if ($bruto instanceof \DateTimeInterface) {
                $data = Carbon::instance($bruto);
            } elseif (trim((string) $bruto) === '') {
                return null;
            } else {
                try {
                    $data = Carbon::createFromFormat('d/m/Y', trim((string) $bruto))->startOfDay();
                } catch (Throwable) {
                    throw new \DomainException('data inválida, use dd/mm/aaaa.');
                }
            }
            if ($data->isFuture() && empty($campo['futuro'])) {
                throw new \DomainException('não pode ser uma data futura.');
            }

            return $data->toDateString();
        }

        $texto = trim((string) $bruto);
        if ($texto === '') {
            return $tipo === 'bool' ? false : null;
        }
        if ($tipo === 'text') {
            return mb_substr($texto, 0, 160);
        }
        if ($tipo === 'bool') {
            return in_array(Norm::normalize($texto), ['sim', 's', '1', 'true', 'x'], true);
        }
        if ($tipo === 'enum') {
            foreach ($campo['enum'] as $opcao) {
                if (Norm::normalize($opcao) === Norm::normalize($texto)) {
                    return $campo['key'] === 'tipo' ? $opcao : $opcao;
                }
            }
            if ($campo['key'] === 'categoria') {
                return (string) config('intelligence.suprimentos.categoria_padrao'); // categoria fora da lista cai em "Outros"
            }
            throw new \DomainException('use um destes valores: '.implode(', ', $campo['enum']).'.');
        }

        $n = str_replace(['R$', '%', ' '], '', $texto);
        $n = str_contains($n, ',') ? str_replace(',', '.', str_replace('.', '', $n)) : $n;
        if (! is_numeric($n)) {
            throw new \DomainException('valor numérico inválido.');
        }
        $n = (float) $n;
        if ($n < 0) {
            throw new \DomainException('não pode ser negativo.');
        }
        if ($tipo === 'int') {
            if ($n != floor($n)) {
                throw new \DomainException('deve ser um número inteiro.');
            }

            return (int) $n;
        }

        return $n;
    }

    /** @param  class-string  $modelo */
    private function gravar(string $modelo, string $tenantId, string $cargaId, array $linhas): void
    {
        $campos = array_flip((new $modelo)->getFillable());
        foreach (array_chunk($linhas, 500) as $lote) {
            $modelo::withoutGlobalScopes()->insert(array_map(fn ($l) => ['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'carga_id' => $cargaId] + array_intersect_key($l, $campos), $lote));
        }
    }
}
