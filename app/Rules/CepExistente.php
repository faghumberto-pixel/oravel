<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

/**
 * Confere se o CEP existe (ViaCEP). O Asaas recusa o checkout com "O campo postalCode é inválido" quando o CEP não existe
 * (caso real de 18/10/2026: 13080-580), e o cliente ficava sem link de pagamento. Se a consulta estiver fora do ar (após uma nova tentativa), o cadastro segue sem a checagem (decisão do dono: uma queda de terceiro não trava a venda).
 */
class CepExistente implements ValidationRule
{
    /** @return bool|null true = existe, false = não existe, null = não deu para saber */
    public static function existe(string $cep): ?bool
    {
        $digitos = preg_replace('/\D/', '', $cep);

        if (! config('services.cep_lookup.ativo', true) || strlen($digitos) !== 8) {
            return null;
        }

        $r = null;
        for ($tentativa = 0; $tentativa < 2 && ! $r?->successful() && $r?->status() !== 400; $tentativa++) {
            try {
                $r = Http::timeout(3)->acceptJson()->get("https://viacep.com.br/ws/{$digitos}/json/");
            } catch (\Throwable) {
                $r = null;
            }
        }

        if ($r === null) {
            return null;
        }

        if ($r->status() === 400) {
            return false; // formato recusado pelo ViaCEP
        }

        if ($r->failed()) {
            return null;
        }

        return $r->json('erro') ? false : true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! config('services.cep_lookup.ativo', true) || strlen(preg_replace('/\D/', '', $value)) !== 8) {
            return;
        }

        if (self::existe($value) === false) {
            $fail('Não encontramos esse CEP. Confira o número e tente de novo.');
        }
    }
}
