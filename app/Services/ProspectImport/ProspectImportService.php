<?php

namespace App\Services\ProspectImport;

use App\Models\Prospect;
use App\Services\CrmImport\CrmImportDestinos;
use App\Services\CrmImport\RelatorioImportacao;
use App\Support\Cnpj;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importacao de prospects pela Central (so super admin). Le o arquivo ja convertido em cabecalhos + linhas
 * (ProspectImportLeitor), liga as colunas aos campos, valida/normaliza cada empresa e grava.
 *
 * Regras que nao mudam:
 *  - a empresa e' identificada pelo CNPJ (valido, com ou sem pontuacao); repetido no arquivo conta uma vez;
 *  - NUNCA apaga dado: celula em branco nao limpa nada;
 *  - NUNCA mexe no que o comercial preencheu (situacao, resultado, observacoes, proximos contatos, vinculo
 *    com lead): so os campos da empresa que vieram mapeados;
 *  - simulacao (nao grava) e importacao usam exatamente a mesma logica.
 */
class ProspectImportService
{
    public const SE_IGNORAR = 'ignorar';

    public const SE_COMPLETAR = 'completar';

    public const SE_ATUALIZAR = 'atualizar';

    /**
     * Campos importaveis. 'sinonimos' sao nomes de coluna reconhecidos sozinhos (sem acento/maiuscula), inclusive as
     * letras do formato compacto da lista antiga (n, l, c, s, k, a, t, tel, em, p, pr, sc, rg, nv).
     *
     * @var array<string, array{rotulo: string, sinonimos: list<string>, obrigatorio: bool}>
     */
    public const CAMPOS = [
        'cnpj' => ['rotulo' => 'CNPJ', 'sinonimos' => ['cnpj', 'cnpj/cpf', 'documento', 'cnpj da empresa'], 'obrigatorio' => true],
        'nome' => ['rotulo' => 'Nome (fantasia)', 'sinonimos' => ['nome', 'nome fantasia', 'fantasia', 'empresa', 'nome da empresa', 'n'], 'obrigatorio' => false],
        'razao_social' => ['rotulo' => 'Razão social', 'sinonimos' => ['razao social', 'razao', 'nome empresarial', 'l'], 'obrigatorio' => false],
        'cidade' => ['rotulo' => 'Cidade', 'sinonimos' => ['cidade', 'municipio', 'c'], 'obrigatorio' => false],
        'porte' => ['rotulo' => 'Porte', 'sinonimos' => ['porte', 'porte da empresa', 's'], 'obrigatorio' => false],
        'capital' => ['rotulo' => 'Capital social', 'sinonimos' => ['capital', 'capital social', 'k'], 'obrigatorio' => false],
        'data_abertura' => ['rotulo' => 'Data de abertura', 'sinonimos' => ['data de abertura', 'data abertura', 'abertura', 'data inicio atividade', 'data de inicio da atividade', 'data de inicio de atividade', 'inicio da atividade'], 'obrigatorio' => false],
        'ano_abertura' => ['rotulo' => 'Ano de abertura', 'sinonimos' => ['ano de abertura', 'ano abertura', 'ano', 'a'], 'obrigatorio' => false],
        'cnae_codigo' => ['rotulo' => 'CNAE (código)', 'sinonimos' => ['cnae', 'cnae principal', 'cnae fiscal', 'cnae codigo', 'codigo cnae', 'cnae fiscal principal', 'codigo da atividade'], 'obrigatorio' => false],
        'atividade' => ['rotulo' => 'Atividade (descrição do CNAE)', 'sinonimos' => ['atividade', 'descricao cnae', 'cnae descricao', 'descricao do cnae', 'cnae fiscal descricao', 'descricao da atividade', 'atividade principal', 'segmento', 't'], 'obrigatorio' => false],
        'telefone' => ['rotulo' => 'Telefone', 'sinonimos' => ['telefone', 'telefone 1', 'fone', 'celular', 'ddd telefone 1', 'tel'], 'obrigatorio' => false],
        'email' => ['rotulo' => 'E-mail', 'sinonimos' => ['email', 'e mail', 'correio eletronico', 'em'], 'obrigatorio' => false],
        'socio' => ['rotulo' => 'Sócio', 'sinonimos' => ['socio', 'nome do socio', 'socio administrador', 'p'], 'obrigatorio' => false],
        'socio_cargo' => ['rotulo' => 'Cargo do sócio', 'sinonimos' => ['qualificacao do socio', 'cargo do socio', 'cargo', 'qualificacao', 'pr'], 'obrigatorio' => false],
        'nota' => ['rotulo' => 'Nota (0 a 10)', 'sinonimos' => ['nota', 'score', 'pontuacao', 'sc'], 'obrigatorio' => false],
        'regiao_campinas' => ['rotulo' => 'Região de Campinas (sim/não)', 'sinonimos' => ['regiao de campinas', 'regiao campinas', 'rg'], 'obrigatorio' => false],
        'empresa_nova' => ['rotulo' => 'Empresa nova (sim/não)', 'sinonimos' => ['empresa nova', 'nova', 'nv'], 'obrigatorio' => false],
        'mensagem_personalizada' => ['rotulo' => 'Mensagem personalizada', 'sinonimos' => ['mensagem', 'mensagem personalizada', 'msg'], 'obrigatorio' => false],
        'o_que_olhar' => ['rotulo' => 'O que olhar antes', 'sinonimos' => ['o que olhar', 'o que olhar antes', 'ver'], 'obrigatorio' => false],
    ];

    /** Tamanhos maximos (iguais aos do formulario do prospect). */
    private const LIMITES = ['nome' => 200, 'razao_social' => 255, 'cidade' => 120, 'porte' => 20, 'atividade' => 160, 'telefone' => 40, 'email' => 200, 'socio' => 200, 'socio_cargo' => 120, 'cnae_codigo' => 12];

    /** Ano a partir do qual a empresa conta como "nova" quando a planilha nao diz (mesmo corte da lista original). */
    public const ANO_EMPRESA_NOVA = 2023;

    /**
     * @param  array<int, string>  $cabecalhos
     * @return array<string, int|null> campo => indice da coluna
     */
    public static function sugerirMapeamento(array $cabecalhos): array
    {
        $normalizados = array_map([CrmImportDestinos::class, 'normalizar'], $cabecalhos);
        $usadas = [];
        $mapa = [];

        foreach (self::CAMPOS as $chave => $def) {
            $candidatos = array_map([CrmImportDestinos::class, 'normalizar'], [$def['rotulo'], ...$def['sinonimos']]);
            $mapa[$chave] = null;

            foreach ($normalizados as $i => $nome) {
                if (! in_array($i, $usadas, true) && in_array($nome, $candidatos, true)) {
                    $mapa[$chave] = $i;
                    $usadas[] = $i;
                    break;
                }
            }
        }

        return $mapa;
    }

    /** CSV de exemplo (cabecalhos reconhecidos + 1 linha), pra o usuario montar a planilha. */
    public static function modeloCsv(): string
    {
        $cab = ['CNPJ', 'Nome fantasia', 'Razão social', 'Cidade', 'Porte', 'Capital social', 'Data de abertura', 'CNAE', 'Atividade', 'Telefone', 'E-mail', 'Sócio', 'Cargo do sócio'];
        $ex = ['43.368.422/0001-27', 'Exemplo Locações', 'Exemplo Locações de Equipamentos Ltda', 'Campinas', 'EPP', '500000', '15/03/2018', '7739-0/99', 'Locação de máquinas para construção', '(19) 3272-0000', 'contato@exemplo.com.br', 'Maria da Silva', 'Sócio-Administrador'];

        return "\xEF\xBB\xBF".implode(';', $cab)."\n".implode(';', array_map(fn ($v) => str_contains($v, ';') ? '"'.$v.'"' : $v, $ex))."\n";
    }

    // ------------------------------------------------------------------ conversao (pura, sem banco)

    /**
     * Normaliza uma linha ja ligada aos campos. O nome NAO e' exigido aqui: so empresa nova precisa dele
     * (quem ja existe so recebe os campos que vieram), e isso e' conferido em processar().
     *
     * @param  array<string, string>  $bruto  campo => texto da celula (so os campos mapeados)
     * @return array{dados: array<string, mixed>, derivados: array<string, mixed>, avisos: list<string>, erro: ?string}
     */
    public static function converter(array $bruto): array
    {
        $dados = [];
        $avisos = [];
        $texto = fn (string $c) => trim((string) ($bruto[$c] ?? ''));

        // --- CNPJ (Excel perde zeros a esquerda: 12 ou 13 digitos viram 14 com zeros, e o digito verificador confere)
        $digitos = Cnpj::limpar($texto('cnpj'));
        if ($digitos === '') {
            return ['dados' => [], 'derivados' => [], 'avisos' => [], 'erro' => 'CNPJ em branco'];
        }
        if (strlen($digitos) >= 12 && strlen($digitos) < 14) {
            $digitos = str_pad($digitos, 14, '0', STR_PAD_LEFT);
            $avisos[] = 'zeros à esquerda do CNPJ restaurados';
        }
        if (! Cnpj::valido($digitos)) {
            return ['dados' => [], 'derivados' => [], 'avisos' => [], 'erro' => 'CNPJ inválido: '.$texto('cnpj')];
        }
        $dados['cnpj'] = Cnpj::formatar($digitos);

        // --- textos simples
        foreach (['nome', 'razao_social', 'cidade', 'atividade', 'socio', 'socio_cargo', 'mensagem_personalizada', 'o_que_olhar'] as $campo) {
            if (($v = $texto($campo)) !== '') {
                $dados[$campo] = isset(self::LIMITES[$campo]) ? mb_substr($v, 0, self::LIMITES[$campo]) : $v;
            }
        }

        if (($v = $texto('porte')) !== '') {
            $dados['porte'] = self::porte($v);
        }

        if (($v = $texto('capital')) !== '') {
            $capital = self::numero($v);
            if ($capital === null) {
                $avisos[] = "capital ignorado ({$v})";
            } else {
                $dados['capital'] = $capital;
            }
        }

        // --- data de abertura (completa) e ano (derivado dela; sozinho quando so veio o ano)
        if (($v = $texto('data_abertura')) !== '') {
            $data = self::data($v);
            if ($data === null) {
                $avisos[] = "data de abertura ignorada ({$v})";
            } else {
                $dados['data_abertura'] = $data;
                $dados['ano_abertura'] = (int) substr($data, 0, 4);
            }
        }
        if (! isset($dados['ano_abertura']) && ($v = $texto('ano_abertura')) !== '') {
            $ano = (int) preg_replace('/\D/', '', $v);
            if ($ano >= 1800 && $ano <= 2100) {
                $dados['ano_abertura'] = $ano;
            } else {
                $avisos[] = "ano de abertura ignorado ({$v})";
            }
        }

        if (($v = $texto('cnae_codigo')) !== '') {
            $so = preg_replace('/\D/', '', $v);
            if (strlen($so) === 7) {
                $dados['cnae_codigo'] = substr($so, 0, 4).'-'.substr($so, 4, 1).'/'.substr($so, 5, 2);
            } else {
                $dados['cnae_codigo'] = mb_substr($v, 0, self::LIMITES['cnae_codigo']);
                $avisos[] = "CNAE com tamanho fora do padrão ({$v})";
            }
        }

        if (($v = $texto('telefone')) !== '') {
            $dados['telefone'] = mb_substr(self::telefone($v), 0, self::LIMITES['telefone']);
        }

        if (($v = $texto('email')) !== '') {
            $email = mb_strtolower($v);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $dados['email'] = mb_substr($email, 0, self::LIMITES['email']);
            } else {
                $avisos[] = "e-mail inválido ignorado ({$v})";
            }
        }

        if (($v = $texto('nota')) !== '') {
            if (is_numeric(str_replace(',', '.', $v))) {
                $dados['nota'] = max(0, min(10, (int) round((float) str_replace(',', '.', $v))));
            } else {
                $avisos[] = "nota ignorada ({$v})";
            }
        }

        foreach (['regiao_campinas', 'empresa_nova'] as $campo) {
            if (($v = $texto($campo)) !== '') {
                $b = self::booleano($v);
                if ($b === null) {
                    $avisos[] = "{$campo} ignorado ({$v})";
                } else {
                    $dados[$campo] = $b;
                }
            }
        }

        // Derivados valem SO para empresa criada agora (empresa que ja existe nunca recebe nome/flag deduzidos).
        $derivados = [];
        if (blank($dados['nome'] ?? null) && filled($dados['razao_social'] ?? null)) {
            $derivados['nome'] = mb_substr($dados['razao_social'], 0, self::LIMITES['nome']);
        }
        // Empresa nova: so deduz da data/ano quando a planilha nao informou.
        if (! array_key_exists('empresa_nova', $dados) && isset($dados['ano_abertura'])) {
            $derivados['empresa_nova'] = $dados['ano_abertura'] >= self::ANO_EMPRESA_NOVA;
        }

        return ['dados' => $dados, 'derivados' => $derivados, 'avisos' => $avisos, 'erro' => null];
    }

    public static function porte(string $v): string
    {
        $t = mb_strtoupper(Str::ascii(trim($v)));

        return match (true) {
            in_array($t, ['01', '1'], true) => 'ME',
            in_array($t, ['03', '3'], true) => 'EPP',
            in_array($t, ['05', '5'], true) => 'DEMAIS',
            str_contains($t, 'MICROEMPREENDEDOR') || $t === 'MEI' => 'MEI',
            str_contains($t, 'PEQUENO') || $t === 'EPP' => 'EPP',
            str_contains($t, 'MICRO') || $t === 'ME' => 'ME',
            str_contains($t, 'DEMAIS') || str_contains($t, 'MEDIO') || str_contains($t, 'GRANDE') => 'DEMAIS',
            default => mb_substr($t, 0, self::LIMITES['porte']),
        };
    }

    /** "1.234.567,89" (pt-BR), "1234567.89" ou "R$ 480000000" -> inteiro em reais; nulo se nao for numero. */
    public static function numero(string $v): ?int
    {
        $t = trim(str_ireplace(['R$', ' '], '', $v));

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $t)) {
            $t = str_replace(',', '.', str_replace('.', '', $t));
        } elseif (str_contains($t, ',') && ! str_contains($t, '.')) {
            $t = str_replace(',', '.', $t);
        }

        return is_numeric($t) && (float) $t >= 0 ? (int) round((float) $t) : null;
    }

    /** dd/mm/aaaa, dd-mm-aaaa, aaaa-mm-dd (com ou sem hora) -> aaaa-mm-dd; nulo se invalida ou fora de 1800-2100. */
    public static function data(string $v): ?string
    {
        $v = trim($v);

        foreach (['/^(\d{4})-(\d{2})-(\d{2})/' => 'ymd', '/^(\d{2})[\/.-](\d{2})[\/.-](\d{4})/' => 'dmy', '/^(\d{4})\/(\d{2})\/(\d{2})/' => 'ymd'] as $padrao => $ordem) {
            if (preg_match($padrao, $v, $m)) {
                [$a, $mes, $d] = $ordem === 'ymd' ? [(int) $m[1], (int) $m[2], (int) $m[3]] : [(int) $m[3], (int) $m[2], (int) $m[1]];

                return $a >= 1800 && $a <= 2100 && checkdate($mes, $d, $a) ? sprintf('%04d-%02d-%02d', $a, $mes, $d) : null;
            }
        }

        return null;
    }

    public static function telefone(string $v): string
    {
        $d = preg_replace('/\D/', '', $v);

        if (strlen($d) >= 12 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }

        return match (strlen($d)) {
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            default => trim($v),
        };
    }

    public static function booleano(string $v): ?bool
    {
        return match (mb_strtolower(Str::ascii(trim($v)))) {
            'sim', 's', '1', 'true', 'verdadeiro', 'x', 'yes', 'y' => true,
            'nao', 'n', '0', 'false', 'falso', 'no' => false,
            default => null,
        };
    }

    // ------------------------------------------------------------------ processamento

    /**
     * @param  array<int, array<int, string>>  $linhas  numero da linha no arquivo => valores por coluna
     * @param  array<string, int|string|null>  $mapeamento  campo => indice da coluna
     * @param  array{se_existir?: string, origem?: ?string, lote?: int|string|null}  $opcoes
     */
    public function processar(array $linhas, array $mapeamento, array $opcoes, bool $simular): RelatorioImportacao
    {
        $relatorio = new RelatorioImportacao($simular);
        $seExiste = $opcoes['se_existir'] ?? self::SE_COMPLETAR;
        $origem = filled($opcoes['origem'] ?? null) ? mb_substr(trim((string) $opcoes['origem']), 0, 120) : null;
        $lote = filled($opcoes['lote'] ?? null) ? max(1, (int) $opcoes['lote']) : null;

        // 1) converte tudo (sem banco) e separa erro / repetido no arquivo
        $validas = [];
        $vistos = [];
        foreach ($linhas as $numero => $celulas) {
            $c = self::converter($this->extrairBruto($celulas, $mapeamento));
            $nome = (string) ($c['dados']['nome'] ?? $c['derivados']['nome'] ?? $c['dados']['razao_social'] ?? ($c['dados']['cnpj'] ?? ''));

            if ($c['erro'] !== null) {
                $relatorio->adicionar($numero, RelatorioImportacao::ERRO, $nome, $c['erro']);

                continue;
            }

            $chave = Cnpj::limpar($c['dados']['cnpj']);
            if (isset($vistos[$chave])) {
                $relatorio->adicionar($numero, RelatorioImportacao::IGNORADO, $nome, "CNPJ repetido no arquivo (já está na linha {$vistos[$chave]})");

                continue;
            }
            $vistos[$chave] = $numero;
            $validas[$numero] = ['chave' => $chave, 'nome' => $nome, ...$c];
        }

        if ($validas === []) {
            return $relatorio;
        }

        // 2) quem ja existe (por CNPJ, com ou sem pontuacao)
        $existentes = $this->carregarExistentes(array_column($validas, 'chave'));

        $aplicar = function () use ($validas, $existentes, $relatorio, $seExiste, $origem, $lote, $simular) {
            foreach ($validas as $numero => $v) {
                /** @var ?Prospect $atual */
                $atual = $existentes[$v['chave']] ?? null;
                $avisos = count($v['avisos']);
                $obs = $v['avisos'] ? implode('; ', $v['avisos']) : '';

                if (! $atual) {
                    $novo = $v['dados'] + $v['derivados'] + array_filter(['origem' => $origem, 'lote' => $lote], fn ($x) => $x !== null);

                    if (blank($novo['nome'] ?? null)) {
                        $relatorio->adicionar($numero, RelatorioImportacao::ERRO, $v['nome'], 'empresa nova sem nome nem razão social');

                        continue;
                    }

                    if (! $simular) {
                        Prospect::create($novo);
                    }
                    $relatorio->adicionar($numero, RelatorioImportacao::CRIADO, $v['nome'], $obs, $avisos);

                    continue;
                }

                if ($seExiste === self::SE_IGNORAR) {
                    $relatorio->adicionar($numero, RelatorioImportacao::IGNORADO, $v['nome'], 'já existe (mantido como está)', $avisos);

                    continue;
                }

                $mudancas = $this->mudancas($atual, $v['dados'], $seExiste);
                if ($lote !== null && ($seExiste === self::SE_ATUALIZAR || blank($atual->lote)) && (int) $atual->lote !== $lote) {
                    $mudancas['lote'] = $lote;
                }

                if ($mudancas === []) {
                    $relatorio->adicionar($numero, RelatorioImportacao::IGNORADO, $v['nome'], $seExiste === self::SE_COMPLETAR ? 'já existe e não há nada novo a completar' : 'já existe e não há mudanças', $avisos);

                    continue;
                }

                if (! $simular) {
                    $atual->update($mudancas);
                }

                $rotulos = collect(array_keys($mudancas))->map(fn ($k) => self::CAMPOS[$k]['rotulo'] ?? ucfirst($k))->implode(', ');
                $relatorio->adicionar($numero, RelatorioImportacao::ATUALIZADO, $v['nome'], ($seExiste === self::SE_COMPLETAR ? 'completou: ' : 'atualizou: ').$rotulos.($obs ? " | {$obs}" : ''), $avisos);
            }
        };

        $simular ? $aplicar() : DB::transaction($aplicar);

        return $relatorio;
    }

    /**
     * O que mudar numa empresa que ja existe. CNPJ nunca muda; so entram campos que vieram na planilha.
     * "completar" so preenche campo vazio; "atualizar" troca quando o valor e' diferente.
     *
     * @param  array<string, mixed>  $novos
     * @return array<string, mixed>
     */
    private function mudancas(Prospect $atual, array $novos, string $modo): array
    {
        unset($novos['cnpj']);
        $mudancas = [];

        foreach ($novos as $campo => $valor) {
            $existente = $atual->{$campo};
            $existente = $existente instanceof \DateTimeInterface ? $existente->format('Y-m-d') : $existente;

            if ($modo === self::SE_COMPLETAR) {
                // booleanos sempre tem valor (false/true): completar nao os toca
                $vazio = ! is_bool($existente) && ($existente === null || $existente === '' || ($campo === 'capital' && (int) $existente === 0));
                if ($vazio) {
                    $mudancas[$campo] = $valor;
                }

                continue;
            }

            if ((string) $existente !== (string) $valor && ! (is_bool($valor) && (bool) $existente === $valor)) {
                $mudancas[$campo] = $valor;
            }
        }

        return $mudancas;
    }

    /**
     * @param  list<string>  $digitos
     * @return array<string, Prospect> CNPJ so digitos => prospect
     */
    private function carregarExistentes(array $digitos): array
    {
        $mapa = [];

        foreach (array_chunk(array_unique($digitos), 500) as $lote) {
            $formas = [];
            foreach ($lote as $d) {
                $formas[] = $d;
                $formas[] = Cnpj::formatar($d);
            }

            foreach (Prospect::query()->whereIn('cnpj', $formas)->get() as $p) {
                $mapa[Cnpj::limpar($p->cnpj)] ??= $p;
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, string>  $celulas
     * @param  array<string, int|string|null>  $mapeamento
     * @return array<string, string>
     */
    private function extrairBruto(array $celulas, array $mapeamento): array
    {
        $bruto = [];
        foreach (array_keys(self::CAMPOS) as $campo) {
            $i = $mapeamento[$campo] ?? null;
            if ($i !== null && $i !== '') {
                $bruto[$campo] = (string) ($celulas[(int) $i] ?? '');
            }
        }

        return $bruto;
    }
}
