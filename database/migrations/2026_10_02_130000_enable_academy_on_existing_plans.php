<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Academia Oravel ('tabela_courses'): pedido do usuario -- ligar em TODOS os contratos
 * existentes. So' marca quem ainda nao tem a chave (nao desfaz escolha explicita feita na
 * Central); contratos novos seguem o fluxo normal e escolhem na tela de contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Plan::withoutGlobalScopes()->get()->each(function (Plan $plan) {
            $features = $plan->features ?? [];

            if (array_key_exists('tabela_courses', $features) || in_array('tabela_courses', $features, true)) {
                return;
            }

            if (array_is_list($features)) {
                $features[] = 'tabela_courses';
            } else {
                $features['tabela_courses'] = true;
            }

            $plan->features = $features;
            $plan->saveQuietly();
        });
    }

    public function down(): void
    {
        Plan::withoutGlobalScopes()->get()->each(function (Plan $plan) {
            $features = $plan->features ?? [];

            if (array_is_list($features)) {
                $features = array_values(array_diff($features, ['tabela_courses']));
            } else {
                unset($features['tabela_courses']);
            }

            $plan->features = $features;
            $plan->saveQuietly();
        });
    }
};
