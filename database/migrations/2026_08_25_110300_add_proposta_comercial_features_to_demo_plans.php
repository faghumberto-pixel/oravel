<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $plans = ['Plano Demo Comercial', 'Plano Demo Nichos'];

    private array $featuresToAdd = ['tabela_proposta_comercial'];

    /**
     * Mesmo padrão de 2026_07_25_185209 (tabela_crm_leads/tabela_quotes) --
     * sem isso a Proposta Comercial fica invisível pra todo mundo em
     * produção, mesmo depois de implementada.
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
