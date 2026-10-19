<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * "Portal do cliente" virou recurso do contrato (grupo Adicionais). Quem já tinha o portal não pode perdê-lo ao publicar:
 * liga a chave em todos os contratos que ainda não a têm (herda vazio). A Central desliga depois, contrato por contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::query()->get() as $plan) {
            $features = $plan->features ?? [];

            if (! array_key_exists('modulo_portal_cliente', $features)) {
                $features['modulo_portal_cliente'] = true;
                $plan->update(['features' => $features]);
            }
        }
    }

    public function down(): void
    {
        // Mantém a chave: remover apagaria escolhas feitas na Central depois da migração.
    }
};
