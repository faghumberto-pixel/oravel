<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FEATURE = 'modulo_app_colaborador';

    /** Liga o App do Colaborador só nos Contratos (plans) que algum tenant usa de fato; os demais ficam como estão. */
    public function up(): void
    {
        $emUso = DB::table('tenants')->whereNotNull('plan_id')->distinct()->pluck('plan_id');

        foreach (DB::table('plans')->whereIn('id', $emUso)->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            // O Contrato guarda os módulos como mapa chave => true (é o que a Central grava e lê).
            if (($features[self::FEATURE] ?? false) === true) {
                continue;
            }

            $features[self::FEATURE] = true;
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        // Sem volta: não dá para saber quais contratos já tinham a feature antes desta migration.
    }
};
