<?php

namespace App\Support\Onboarding;

use App\Models\Tenant;

/** Decide, só com dados reais da empresa, se um passo do onboarding já foi cumprido. Nunca depende de o usuário clicar em "concluí". */
interface Detector
{
    public function concluido(Tenant $tenant): bool;
}
