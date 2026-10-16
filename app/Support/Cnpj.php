<?php

namespace App\Support;

/** Utilidades de CNPJ: limpeza, formatação e validação por dígito verificador. */
class Cnpj
{
    public static function limpar(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public static function formatar(?string $valor): string
    {
        $d = self::limpar($valor);

        return strlen($d) === 14
            ? sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12, 2))
            : $d;
    }

    public static function valido(?string $valor): bool
    {
        $cnpj = self::limpar($valor);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $calcDigit = function (string $base) {
            $weights = array_slice([6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], -strlen($base));
            $sum = 0;
            foreach (str_split($base) as $i => $digit) {
                $sum += (int) $digit * $weights[$i];
            }
            $rest = $sum % 11;

            return $rest < 2 ? 0 : 11 - $rest;
        };

        $base = substr($cnpj, 0, 12);
        $d1 = $calcDigit($base);
        $d2 = $calcDigit($base.$d1);

        return $cnpj === $base.$d1.$d2;
    }
}
