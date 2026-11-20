<?php

namespace App\Relacionamento\Console;

use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Services\RegistraInteracao;
use App\Relacionamento\Services\SenderAssinante;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Importa a lista de assinantes exportada do Sender (CSV), sem depender de webhook nem de API (que o plano gratuito
 * pode nao ter). Reconhece as colunas por NOME (portugues/ingles, com ou sem acento), aceita ',' ou ';' e BOM/Latin-1.
 * Idempotente (mesma chave de dedupe do webhook: uma pessoa = uma interacao por tipo).
 *
 * Quem esta descadastrado/inativo NAO e' importado: se ja existe na Central, e' marcado como descadastrado; se
 * nao existe, nao e' criado (nao guardamos dados de quem pediu para sair). Quem voltou a ficar ativo perde a marca.
 */
class ImportarSenderCsv extends Command
{
    protected $signature = 'relacionamento:importar-sender {arquivo : caminho do CSV exportado do Sender}
        {--dry-run : so mostra o que faria}
        {--mapa=* : corrige coluna não reconhecida, no formato "Nome da coluna=campo" (campos: email, nome, sobrenome, telefone, empresa, tags, status, descadastro, criado, origem)}
        {--tipo-padrao= : tipo da interação para linhas sem tag reconhecida (ex.: gazeta_inscricao)}
        {--consentimento-form : trata TODAS as linhas como vindas do formulário (registra o aceite); sem isso só conta quando a coluna origem for FORM}';

    protected $description = 'Importa assinantes exportados do Sender (CSV) para o Relacionamento Oravel';

    private const ALIASES = [
        'email' => ['email', 'emailaddress', 'enderecodeemail', 'correioeletronico', 'mail'],
        'nome' => ['firstname', 'primeironome', 'nome', 'name', 'nomecompleto', 'fullname'],
        'sobrenome' => ['lastname', 'sobrenome', 'ultimonome'],
        'telefone' => ['phone', 'telefone', 'celular', 'mobile', 'whatsapp', 'fone', 'phonenumber'],
        'empresa' => ['empresa', 'company', 'companyname', 'nomedaempresa'],
        'tags' => ['tags', 'tag', 'groups', 'grupos', 'group', 'grupo', 'subscribertags'],
        'status' => ['status', 'subscriberstatus', 'situacao', 'emailstatus', 'estado'],
        'descadastro' => ['unsubscribedat', 'unsubscribed', 'datadedescadastro', 'descadastradoem', 'unsubscribeddate'],
        'criado' => ['created', 'createdat', 'datecreated', 'subscribedat', 'datadecriacao', 'datadeinscricao', 'criadoem', 'createdutc', 'signupdate'],
        'origem' => ['source', 'origem', 'subscribersource'],
    ];

    private const ATIVOS = ['', 'active', 'ativo', 'ativa', 'subscribed', 'inscrito', 'confirmed', 'confirmado'];

    public function handle(SenderAssinante $conversor, RegistraInteracao $registra): int
    {
        $linhas = $this->ler((string) $this->argument('arquivo'));
        if ($linhas === null) {
            return self::FAILURE;
        }

        [$cabecalho, $registros] = $linhas;
        $mapa = $this->mapear($cabecalho);
        $this->mostrarMapa($cabecalho, $mapa);

        if (! isset($mapa['email'])) {
            $this->error('Não encontrei a coluna de e-mail. Use --mapa="Nome da coluna=email". Colunas do arquivo: '.implode(' | ', $cabecalho));

            return self::FAILURE;
        }

        $tipoPadrao = $this->option('tipo-padrao') ?: null;
        if ($tipoPadrao !== null && ! isset(RelInteracao::TIPOS[$tipoPadrao])) {
            $this->error('--tipo-padrao inválido. Tipos: '.implode(', ', array_keys(RelInteracao::TIPOS)));

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $r = ['linhas' => 0, 'invalidas' => 0, 'importadas' => 0, 'ja_existiam' => 0, 'sem_tag' => 0, 'inativos_ignorados' => 0, 'descadastrados' => 0, 'reativados' => 0];
        $tagsSemRegra = [];

        foreach ($registros as $linha) {
            $r['linhas']++;
            $v = fn (string $campo): ?string => isset($mapa[$campo]) ? (trim((string) ($linha[$mapa[$campo]] ?? '')) ?: null) : null;

            $email = RegistraInteracao::normalizaEmail($v('email'));
            if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $r['invalidas']++;

                continue;
            }

            if ($this->inativo($v('status'), $v('descadastro'))) {
                $contato = RelContato::where('email', $email)->first();
                if ($contato) {
                    if (! $dry && $contato->descadastrado_em === null) {
                        $contato->update(['descadastrado_em' => $this->data($v('descadastro')) ?? now()]);
                    }
                    $r['descadastrados']++;
                } else {
                    $r['inativos_ignorados']++;
                }

                continue;
            }

            $tags = $this->separarTags($v('tags'));
            $payload = [
                'email' => $email, 'firstname' => $v('nome'), 'lastname' => $v('sobrenome'), 'phone' => $v('telefone'),
                'source' => $this->option('consentimento-form') ? 'FORM' : ($v('origem') ? Str::upper($v('origem')) : null),
                'subscriber_tags' => array_map(fn ($t) => ['title' => $t], $tags),
                'columns' => $v('empresa') ? [['title' => 'Empresa', 'value' => $v('empresa')]] : [],
                'created_local' => $v('criado'),
            ];

            $interacoes = $conversor->interacoes($payload, $tipoPadrao);
            if ($interacoes === []) {
                $r['sem_tag']++;
                foreach ($tags as $t) {
                    $tagsSemRegra[$t] = ($tagsSemRegra[$t] ?? 0) + 1;
                }

                continue;
            }

            $novo = false;
            foreach ($interacoes as $dados) {
                $novo = $novo || ! RelInteracao::where('dedupe_key', $dados['dedupe_key'])->exists();
                if (! $dry) {
                    $registra->registrar($dados);
                }
            }
            $novo ? $r['importadas']++ : $r['ja_existiam']++;

            // Linha vinda de formulario (ou afirmada com --consentimento-form) completa o aceite de quem ja foi importado antes.
            if (! $dry && $payload['source'] === 'FORM' && ($c = RelContato::where('email', $email)->first()) && $c->consentimento_em === null) {
                $c->update(['consentimento_em' => now(), 'consentimento_origem' => 'sender-form']);
            }

            // Voltou a ficar ativo no Sender: a marca de descadastro deixa de valer.
            if (! $dry && ($contato = RelContato::where('email', $email)->first()) && $contato->descadastrado_em !== null) {
                $contato->update(['descadastrado_em' => null]);
                $r['reativados']++;
            }
        }

        $this->relatorio($r, $tagsSemRegra, $dry);

        return self::SUCCESS;
    }

    /** @return array{0: list<string>, 1: list<array<int,string>>}|null */
    private function ler(string $arquivo): ?array
    {
        if (! is_file($arquivo) || ! is_readable($arquivo)) {
            $this->error("Arquivo não encontrado ou sem permissão de leitura: {$arquivo}");

            return null;
        }

        $conteudo = (string) file_get_contents($arquivo);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }

        $primeira = strtok($conteudo, "\n") ?: '';
        $sep = substr_count($primeira, ';') > substr_count($primeira, ',') ? ';' : ',';

        $h = fopen('php://temp', 'r+');
        fwrite($h, $conteudo);
        rewind($h);
        $cabecalho = fgetcsv($h, 0, $sep);
        if (! $cabecalho || count(array_filter($cabecalho, 'strlen')) === 0) {
            fclose($h);
            $this->error('O arquivo está vazio ou sem linha de cabeçalho.');

            return null;
        }

        $registros = [];
        while (($l = fgetcsv($h, 0, $sep)) !== false) {
            if ($l === [null] || count(array_filter($l, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $registros[] = $l;
        }
        fclose($h);

        return [array_map(fn ($c) => trim((string) $c), $cabecalho), $registros];
    }

    /** @return array<string,int> campo => indice da coluna */
    private function mapear(array $cabecalho): array
    {
        $norm = fn (string $s): string => preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($s)));
        $mapa = [];

        // 1) Correcoes explicitas (--mapa) vencem qualquer reconhecimento automatico.
        foreach ((array) $this->option('mapa') as $par) {
            [$coluna, $campo] = array_pad(explode('=', (string) $par, 2), 2, '');
            $i = array_search($norm($coluna), array_map($norm, $cabecalho), true);
            if ($i !== false && isset(self::ALIASES[trim($campo)])) {
                $mapa[trim($campo)] = $i;
            }
        }

        // 2) Reconhecimento por nome (a primeira coluna que casar com cada campo).
        foreach ($cabecalho as $i => $nome) {
            foreach (self::ALIASES as $campo => $aliases) {
                if (! isset($mapa[$campo]) && in_array($norm($nome), $aliases, true) && ! in_array($i, $mapa, true)) {
                    $mapa[$campo] = $i;
                }
            }
        }

        return $mapa;
    }

    private function mostrarMapa(array $cabecalho, array $mapa): void
    {
        $this->line('Colunas reconhecidas: '.(collect($mapa)->map(fn ($i, $campo) => "{$campo} ← \"{$cabecalho[$i]}\"")->implode(', ') ?: 'nenhuma'));
        $ignoradas = collect($cabecalho)->filter(fn ($n, $i) => ! in_array($i, $mapa, true))->values();
        if ($ignoradas->isNotEmpty()) {
            $this->line('Colunas ignoradas: '.$ignoradas->implode(', '));
        }
    }

    private function inativo(?string $status, ?string $descadastro): bool
    {
        if ($descadastro !== null && ! in_array(mb_strtolower($descadastro), ['0', 'false', 'nao', 'não', 'no', '-', 'null'], true)) {
            return true;
        }

        return ! in_array(mb_strtolower((string) $status), self::ATIVOS, true);
    }

    /** @return list<string> */
    private function separarTags(?string $tags): array
    {
        return $tags === null ? [] : array_values(array_filter(array_map('trim', preg_split('/[;,|]/', $tags))));
    }

    private function data(?string $valor): ?Carbon
    {
        try {
            return $valor ? Carbon::parse($valor, config('app.timezone')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function relatorio(array $r, array $tagsSemRegra, bool $dry): void
    {
        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '').'Resultado da importação');
        $this->table(['Item', 'Quantidade'], [
            ['Linhas lidas', $r['linhas']],
            [$dry ? 'Seriam importadas (novas)' : 'Importadas (novas)', $r['importadas']],
            ['Já existiam (nada a fazer)', $r['ja_existiam']],
            ['Descadastrados já conhecidos (marcados)', $r['descadastrados']],
            ['Inativos/descadastrados desconhecidos (não importados)', $r['inativos_ignorados']],
            ['Reativados (voltaram a ficar ativos)', $r['reativados']],
            ['Ignoradas por e-mail inválido', $r['invalidas']],
            ['Ignoradas por tag sem regra', $r['sem_tag']],
        ]);

        if ($tagsSemRegra !== []) {
            arsort($tagsSemRegra);
            $this->warn('Tags sem regra (linhas ignoradas por causa delas): '.collect($tagsSemRegra)->map(fn ($n, $t) => "\"{$t}\" ({$n})")->implode(', '));
            $this->line('Para incluí-las: --tipo-padrao=gazeta_inscricao (todas as linhas sem tag reconhecida) ou uma regra em config/relacionamento.php (sender_tags).');
        }
    }
}
