<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FEATURE = 'tabela_crm_campos_custom';

    /** Liga "Campos Personalizados" só nos Contratos (plans) que já têm CRM Leads ou Clientes. */
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            if (($features['tabela_crm_leads'] ?? false) !== true && ($features['tabela_clients'] ?? false) !== true) {
                continue;
            }

            $features[self::FEATURE] = true;
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            if (! array_key_exists(self::FEATURE, $features)) {
                continue;
            }

            unset($features[self::FEATURE]);
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }
};
