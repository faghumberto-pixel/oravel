<?php

namespace App\Rules;

use App\Support\Cnpj;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF (11 dígitos) ou CNPJ (14 dígitos) por dígito verificador --
 * aceita qualquer formatação (com ou sem pontuação), calcula em cima
 * dos dígitos puros.
 */
class CpfCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) === 11) {
            if (! $this->isValidCpf($digits)) {
                $fail('O :attribute informado não é um CPF válido.');
            }

            return;
        }

        if (strlen($digits) === 14) {
            if (! $this->isValidCnpj($digits)) {
                $fail('O :attribute informado não é um CNPJ válido.');
            }

            return;
        }

        $fail('O :attribute deve ser um CPF ou CNPJ válido.');
    }

    private function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = (($sum * 10) % 11) % 10;

            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        return Cnpj::valido($cnpj);
    }
}
