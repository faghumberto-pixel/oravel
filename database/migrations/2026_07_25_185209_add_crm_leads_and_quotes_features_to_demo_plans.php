<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $plans = ['Plano Demo Comercial', 'Plano Demo Nichos'];

    private array $featuresToAdd = ['tabela_crm_leads', 'tabela_quotes'];

    /**
     * tabela_crm_leads nao existe em NENHUM plano (Leads/Funil/Mapa do CRM
     * ficaram invisiveis pra todo mundo) e tabela_quotes so existia em
     * planos de teste avulsos -- nenhum dos planos "de verdade" usados
     * pelos tenants demo tinha. Menus do Comercial reportados como
     * "faltando" pelo usuario, 2026-07-25.
     */
    public function up(): void
    {
        foreach ($this->plans as $planName) {
            $plan = DB::table('plans')->where('name', $planName)->first();

            if (! $plan) {
                continue;
            }

            $features = json_decode($plan->features, true) ?? [];
            $features = array_values(array_unique([...$features, ...$this->featuresToAdd]));

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->plans as $planName) {
            $plan = DB::table('plans')->where('name', $planName)->first();

            if (! $plan) {
                continue;
            }

            $features = json_decode($plan->features, true) ?? [];
            $features = array_values(array_diff($features, $this->featuresToAdd));

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }
};
