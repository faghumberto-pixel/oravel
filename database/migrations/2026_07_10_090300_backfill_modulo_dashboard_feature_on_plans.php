<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * 'modulo_dashboard' e' uma feature nova (controla App\Filament\Pages\PainelGestao,
 * que ate agora sempre aparecia pra todo mundo sem trava nenhuma). Sem este
 * backfill, todo plano existente perderia o Dashboard de uma vez, ja que
 * Tenant::hasFeature()/Plan::hasFeature() sao opt-in (chave ausente = nega).
 * So marca como concedida pra quem ja existe; dai em diante, desmarcar na
 * Central esconde o Dashboard normalmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Plan::withoutGlobalScopes()->get()->each(function (Plan $plan) {
            $features = $plan->features ?? [];

            if (array_key_exists('modulo_dashboard', $features) || in_array('modulo_dashboard', $features, true)) {
                return;
            }

            if (array_is_list($features)) {
                $features[] = 'modulo_dashboard';
            } else {
                $features['modulo_dashboard'] = true;
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
                $features = array_values(array_diff($features, ['modulo_dashboard']));
            } else {
                unset($features['modulo_dashboard']);
            }

            $plan->features = $features;
            $plan->saveQuietly();
        });
    }
};
