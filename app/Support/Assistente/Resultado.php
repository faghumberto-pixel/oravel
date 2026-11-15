<?php

namespace App\Support\Assistente;

/**
 * Tratamento do resultado das consultas do Assistente antes de ir pra tela, Excel e relatorio.
 */
class Resultado
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Remove colunas de codigo interno (UUID): as chamadas "id"/"*_id" e qualquer coluna cujos
     * valores preenchidos sao todos UUID (ex.: "pedido" = id do pedido). O usuario ve nomes,
     * SKU, patrimonio e numero de OS, nunca o codigo do banco. Se sobraria nada, devolve como veio.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<int, array<string, mixed>>
     */
    public static function semCodigosInternos(array $linhas): array
    {
        if ($linhas === []) {
            return $linhas;
        }

        $remover = [];
        foreach (array_keys($linhas[0]) as $coluna) {
            if (self::nomeDeId((string) $coluna) || self::soUuid(array_column($linhas, $coluna))) {
                $remover[] = $coluna;
            }
        }

        if ($remover === [] || count($remover) === count($linhas[0])) {
            return $linhas;
        }

        return array_map(fn (array $l) => array_diff_key($l, array_flip($remover)), $linhas);
    }

    private static function nomeDeId(string $coluna): bool
    {
        return (bool) preg_match('/^(id|uuid)$|_(id|uuid)$/i', $coluna);
    }

    /** @param  array<int, mixed>  $valores */
    private static function soUuid(array $valores): bool
    {
        $preenchidos = array_filter($valores, fn ($v) => $v !== null && $v !== '');

        if ($preenchidos === []) {
            return false;
        }

        foreach ($preenchidos as $v) {
            if (! is_string($v) || ! preg_match(self::UUID, $v)) {
                return false;
            }
        }

        return true;
    }
}
