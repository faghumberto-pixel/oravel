<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Pega o erro de digitação mais comum no cadastro: domínio de provedor conhecido escrito quase certo (gamil.com, hotmial.com).
 * O Asaas recusa esses endereços ao criar o checkout, e o cliente ficava sem link de pagamento (caso real em 18/10/2026).
 */
class EmailSemErroDeDigitacao implements ValidationRule
{
    /** Provedores muito usados no Brasil. Domínio exatamente igual a um deles é aceito; parecido demais com um deles é recusado. */
    public const PROVEDORES = [
        'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'yahoo.com.br', 'icloud.com', 'live.com', 'uol.com.br', 'bol.com.br', 'terra.com.br', 'msn.com', 'globo.com', 'ig.com.br',
    ];

    public static function sugestao(string $email): ?string
    {
        $dominio = mb_strtolower(trim(substr(strrchr($email, '@') ?: '', 1)));

        if ($dominio === '' || in_array($dominio, self::PROVEDORES, true)) {
            return null;
        }

        foreach (self::PROVEDORES as $provedor) {
            if (levenshtein($dominio, $provedor) <= 2 && abs(strlen($dominio) - strlen($provedor)) <= 1) {
                return $provedor;
            }
        }

        return null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ($sugestao = self::sugestao($value))) {
            $fail('Confira o e-mail: o final "'.substr(strrchr($value, '@'), 1).'" parece digitado errado. Você quis dizer @'.$sugestao.'?');
        }
    }
}
