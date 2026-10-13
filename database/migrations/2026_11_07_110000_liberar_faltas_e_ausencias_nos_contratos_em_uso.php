<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FEATURE = 'tabela_absences';

    /** Liga "Faltas e Ausências" só nos Contratos (plans) que algum tenant usa de fato; os demais ficam como estão. */
    public function up(): void
    {
        $emUso = DB::table('tenants')->whereNotNull('plan_id')->distinct()->pluck('plan_id');

        foreach (DB::table('plans')->whereIn('id', $emUso)->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            if (in_array(self::FEATURE, $features, true)) {
                continue;
            }

            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode([...$features, self::FEATURE])]);
        }
    }

    public function down(): void
    {
        // Sem volta: não dá para saber quais contratos já tinham a feature antes desta migration.
    }
};
