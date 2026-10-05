<?php

namespace App\Support;

use App\Models\Employee;

class Cpf
{
    /** Só os dígitos ("123.456.789-09" -> "12345678909"). */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    /** 11 dígitos, não todos iguais e com os dois dígitos verificadores corretos. */
    public static function isValid(?string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * (($length + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Regra de validação do campo CPF (cadastro de colaborador): CPF válido e
     * ainda não usado por outro colaborador do mesmo tenant. O CPF provisório
     * do sistema (prefixo 00000) só é aceito se for o que o registro já tem.
     */
    public static function rule(?string $tenantId, ?string $ignoreEmployeeId = null, ?string $currentValue = null): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($tenantId, $ignoreEmployeeId, $currentValue) {
            if (blank($value)) {
                return;
            }

            $digits = self::digits($value);
            $isUnchangedPlaceholder = $currentValue !== null && $digits === self::digits($currentValue)
                && str_starts_with($digits, Employee::CPF_PLACEHOLDER_PREFIX);

            if (! $isUnchangedPlaceholder && ! self::isValid($digits)) {
                $fail('CPF inválido. Confira os números (11 dígitos).');

                return;
            }

            if ($tenantId && Employee::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('cpf', $digits)
                ->when($ignoreEmployeeId, fn ($q) => $q->where('id', '!=', $ignoreEmployeeId))
                ->exists()) {
                $fail('Já existe um colaborador com este CPF.');
            }
        };
    }
}
