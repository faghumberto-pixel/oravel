<?php

namespace App\Domain\Intelligence\Trial\Support;

/** WhatsApp brasileiro → E.164. Aceita (19) 99999-9999, 19999999999, +55 19 99999-9999. Devolve null se não for celular BR plausível. */
class TelefoneBr
{
    public static function e164(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', (string) $valor);

        if (str_starts_with($d, '55') && in_array(strlen($d), [12, 13], true)) {
            $d = substr($d, 2);
        }

        if (strlen($d) !== 11) {
            return null;
        }

        $ddd = (int) substr($d, 0, 2);
        if ($ddd < 11 || $ddd % 10 === 0) {
            return null;
        }

        if ($d[2] !== '9') {
            return null; // WhatsApp = celular, 9 dígitos começando em 9
        }

        return '+55'.$d;
    }

    public static function formatar(string $e164): string
    {
        $d = preg_replace('/\D/', '', $e164);
        $d = str_starts_with($d, '55') ? substr($d, 2) : $d;

        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
}
