<?php

namespace App\Support;

use App\Models\Client;

/**
 * Impede cadastro duplicado de cliente dentro da mesma empresa (tenant):
 * razão social, nome fantasia, CNPJ, inscrição estadual e municipal, endereço,
 * telefone/celular e e-mail. A comparação ignora maiúsculas, acentos,
 * pontuação e espaços (ex.: "12.345.678/0001-90" = "12345678000190").
 * Considera também clientes apagados, para não recriar um cadastro que
 * poderia ser restaurado.
 */
class ClientDuplicidade
{
    private const ACENTOS_DE = 'áàâãäéèêëíìîïóòôõöúùûüçñ';

    private const ACENTOS_PARA = 'aaaaaeeeeiiiiooooouuuucn';

    /** Valores que não identificam ninguém (vários clientes podem ser isentos). */
    private const IGNORAR = ['isento', 'isenta', 'naotem', 'nao', 'na', 'nd'];

    /**
     * @param  array<string, mixed>  $dados  campos do cliente (como no formulário)
     * @return array<string, string> campo => mensagem de erro
     */
    public static function conflitos(array $dados, string $tenantId, string|int|null $ignorarId = null): array
    {
        $erros = [];

        $texto = ['name' => 'Razão Social', 'fantasy_name' => 'Nome Fantasia'];
        foreach ($texto as $campo => $rotulo) {
            $valor = self::texto($dados[$campo] ?? null);
            if ($valor !== '' && self::existe($tenantId, $ignorarId, self::sqlTexto($campo), $valor)) {
                $erros[$campo] = "Já existe um cliente com este(a) {$rotulo}.";
            }
        }

        // Razão social e nome fantasia também não podem coincidir entre si em outro cliente.
        foreach (['name' => 'fantasy_name', 'fantasy_name' => 'name'] as $meu => $outro) {
            $valor = self::texto($dados[$meu] ?? null);
            if ($valor !== '' && ! isset($erros[$meu]) && self::existe($tenantId, $ignorarId, self::sqlTexto($outro), $valor)) {
                $erros[$meu] = 'Este nome já é usado por outro cliente.';
            }
        }

        $alfa = ['document' => 'CNPJ/CPF', 'state_registration' => 'Inscrição Estadual', 'municipal_registration' => 'Inscrição Municipal'];
        foreach ($alfa as $campo => $rotulo) {
            $valor = self::alfanumerico($dados[$campo] ?? null);
            if (self::util($valor) && self::existe($tenantId, $ignorarId, self::sqlAlfa($campo), $valor)) {
                $erros[$campo] = "Já existe um cliente com este(a) {$rotulo}.";
            }
        }

        $email = mb_strtolower(trim((string) ($dados['email'] ?? '')));
        if ($email !== '' && self::existe($tenantId, $ignorarId, 'lower(trim(email))', $email)) {
            $erros['email'] = 'Já existe um cliente com este e-mail.';
        }

        foreach (['phone' => 'Telefone', 'whatsapp' => 'Celular / WhatsApp'] as $campo => $rotulo) {
            $numero = preg_replace('/\D+/', '', (string) ($dados[$campo] ?? ''));
            if (strlen($numero) < 8) {
                continue;
            }
            foreach (['phone', 'whatsapp'] as $coluna) {
                if (self::existe($tenantId, $ignorarId, "regexp_replace(coalesce({$coluna}, ''), '\\D', '', 'g')", $numero)) {
                    $erros[$campo] = "Este número já está cadastrado em outro cliente ({$rotulo}).";
                    break;
                }
            }
        }

        $endereco = self::texto($dados['address'] ?? null);
        if ($endereco !== '') {
            $chave = implode('|', [$endereco, self::texto($dados['address_complement'] ?? null), self::texto($dados['city'] ?? null), self::texto($dados['state'] ?? null)]);
            $sql = "concat_ws('|', ".implode(', ', array_map(fn ($c) => self::sqlTexto($c), ['address', 'address_complement', 'city', 'state'])).')';
            if (self::existe($tenantId, $ignorarId, $sql, $chave)) {
                $erros['address'] = 'Já existe um cliente com este endereço.';
            }
        }

        return $erros;
    }

    private static function existe(string $tenantId, string|int|null $ignorarId, string $expressaoSql, string $valor): bool
    {
        return Client::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->whereRaw("{$expressaoSql} = ?", [$valor])
            ->exists();
    }

    /** Minúsculas, sem acento, espaços colapsados. */
    public static function texto(mixed $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));
        $valor = strtr($valor, array_combine(mb_str_split(self::ACENTOS_DE), mb_str_split(self::ACENTOS_PARA)));

        return trim(preg_replace('/\s+/', ' ', $valor));
    }

    public static function alfanumerico(mixed $valor): string
    {
        return preg_replace('/[^a-z0-9]/', '', self::texto($valor));
    }

    private static function util(string $valor): bool
    {
        return strlen($valor) >= 4 && ! in_array($valor, self::IGNORAR, true);
    }

    private static function sqlTexto(string $coluna): string
    {
        return "trim(regexp_replace(translate(lower(coalesce({$coluna}, '')), '".self::ACENTOS_DE."', '".self::ACENTOS_PARA."'), '\\s+', ' ', 'g'))";
    }

    private static function sqlAlfa(string $coluna): string
    {
        return "regexp_replace(".self::sqlTexto($coluna).", '[^a-z0-9]', '', 'g')";
    }
}
