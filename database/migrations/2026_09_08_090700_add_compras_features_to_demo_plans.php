<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * tabela_purchase_orders/tabela_material_requests/tabela_goods_receipts
 * nunca foram adicionadas a NENHUM plano real (nem tabela_suppliers, apesar
 * do model ja ter a chave desde 2026-07-05) -- nao dava pra notar porque os
 * 4 Resources ficaram com shouldRegisterNavigation=false ate' agora (ver
 * migration 2026_09_08_090500). Com a navegacao ligada, sem isso o menu
 * "Compras" apareceria pra depois barrar tudo na TRAVA COMERCIAL SOBERANA
 * de AbstractPolicy -- mesmo problema ja' corrigido uma vez em
 * 2026_07_25_185209_add_crm_leads_and_quotes_features_to_demo_plans.php,
 * mesmo fix aqui.
 */
return new class extends Migration
{
    private array $plans = ['Plano Demo Comercial', 'Plano Demo Nichos'];

    private array $featuresToAdd = [
        'tabela_suppliers',
        'tabela_purchase_orders',
        'tabela_material_requests',
        'tabela_goods_receipts',
        'tabela_supplier_categories',
        'tabela_supplier_contracts',
    ];

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
