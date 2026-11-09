<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * As Visoes gerais e os Paineis de Controle por funcao ganharam chave propria no contrato (modulo_paineis_funcao), para a Central ligar e
 * desligar contrato a contrato. Ate agora so a flag global os controlava, e ela estava ligada: para ninguem perder tela ao publicar, todo
 * contrato existente recebe a chave LIGADA (mesmo padrao do backfill do modulo_dashboard). A Central desmarca onde nao quiser.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::withoutGlobalScopes()->get() as $plan) {
            $features = $plan->features ?? [];

            if (array_key_exists('modulo_paineis_funcao', $features)) {
                continue;
            }

            $features['modulo_paineis_funcao'] = true;
            $plan->features = $features;
            $plan->saveQuietly();
        }
    }

    public function down(): void
    {
        foreach (Plan::withoutGlobalScopes()->get() as $plan) {
            $features = $plan->features ?? [];
            unset($features['modulo_paineis_funcao']);
            $plan->features = $features;
            $plan->saveQuietly();
        }
    }
};
