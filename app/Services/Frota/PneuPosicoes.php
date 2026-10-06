<?php

namespace App\Services\Frota;

use App\Models\Asset;

/**
 * Posições de pneu do veículo. Códigos: E1 = eixo 1; LE/LD = lado esquerdo/direito; em eixo duplo, E = externo e
 * I = interno (ex.: E2-LEE = eixo 2, lado esquerdo, externo). Veículo leve: 4 + estepe; pesado: 3 eixos + estepe.
 */
class PneuPosicoes
{
    /** @return array<string, string> código => rótulo */
    public static function para(Asset $ativo): array
    {
        $posicoes = $ativo->veiculo_pesado
            ? [
                'E1-LE' => 'Eixo 1 · esquerdo', 'E1-LD' => 'Eixo 1 · direito',
                'E2-LEE' => 'Eixo 2 · esquerdo externo', 'E2-LEI' => 'Eixo 2 · esquerdo interno',
                'E2-LDI' => 'Eixo 2 · direito interno', 'E2-LDE' => 'Eixo 2 · direito externo',
                'E3-LEE' => 'Eixo 3 · esquerdo externo', 'E3-LEI' => 'Eixo 3 · esquerdo interno',
                'E3-LDI' => 'Eixo 3 · direito interno', 'E3-LDE' => 'Eixo 3 · direito externo',
            ]
            : [
                'E1-LE' => 'Eixo 1 · esquerdo', 'E1-LD' => 'Eixo 1 · direito',
                'E2-LE' => 'Eixo 2 · esquerdo', 'E2-LD' => 'Eixo 2 · direito',
            ];

        return $posicoes + ['ESTEPE' => 'Estepe'];
    }

    public static function valida(Asset $ativo, string $posicao): bool
    {
        return array_key_exists($posicao, self::para($ativo));
    }
}
