<?php

namespace App\Filament\Pages\Auth;

use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage as BaseTwoFactorPage;

/**
 * O pacote aceita o codigo de recuperacao mas nao o invalida. Aqui cada codigo
 * vale uma unica vez.
 */
class TwoFactorPage extends BaseTwoFactorPage
{
    public function hasValidCode()
    {
        if (! $this->usingRecoveryCode) {
            return parent::hasValidCode();
        }

        $user = filament('filament-breezy')->auth()->user();
        $informed = trim((string) $this->code);
        $codes = $user->two_factor_recovery_codes ?? [];

        if ($informed === '') {
            return false;
        }

        foreach ($codes as $code) {
            if (hash_equals((string) $code, $informed)) {
                $user->breezySession->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(
                        array_values(array_filter($codes, fn ($c) => $c !== $code))
                    )),
                ])->save();

                return true;
            }
        }

        return false;
    }
}
