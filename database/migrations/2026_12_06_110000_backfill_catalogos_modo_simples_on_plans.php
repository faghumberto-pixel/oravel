<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Tipos de ação e problemas comuns ganharam chave no contrato. Liga nos contratos que já têm Ordens de Serviço (quem usa OS usa os
 * cadastros); quem não tem OS fica sem.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::query()->get() as $plan) {
            $features = $plan->features ?? [];
            $comOs = ($features['tabela_maintenance_orders'] ?? false) === true;
            $mudou = false;

            foreach (['tabela_maintenance_action_types', 'tabela_reported_problems'] as $chave) {
                if (! array_key_exists($chave, $features)) {
                    $features[$chave] = $comOs;
                    $mudou = true;
                }
            }

            if ($mudou) {
                $plan->update(['features' => $features]);
            }
        }
    }

    public function down(): void
    {
        // Mantém as chaves: remover apagaria escolhas feitas na Central depois da migração.
    }
};
