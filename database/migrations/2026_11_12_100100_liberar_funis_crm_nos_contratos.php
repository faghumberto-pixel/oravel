<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOVAS = ['tabela_crm_funis', 'tabela_crm_etapas', 'tabela_crm_motivos_perda', 'tabela_crm_origens'];

    /** Liga a configuração do CRM só nos Contratos (plans) que já têm "CRM - Leads"; os demais ficam como estão. */
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            if (($features['tabela_crm_leads'] ?? false) !== true) {
                continue;
            }

            foreach (self::NOVAS as $chave) {
                $features[$chave] = true;
            }

            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?? [];

            if (! array_intersect(self::NOVAS, array_keys($features))) {
                continue;
            }

            foreach (self::NOVAS as $chave) {
                unset($features[$chave]);
            }

            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }
};
