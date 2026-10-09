<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * O WhatsApp passa a ser UM módulo do contrato (modulo_whatsapp), desligado para todos até a Central
 * liberar. Remove as chaves de tela soltas que ele tinha criado ligadas para todo mundo.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::query()->get() as $plan) {
            $features = $plan->features ?? [];

            unset($features['menu_whatsapp'], $features['menu_whatsapp_da_empresa']);

            if (! array_key_exists('modulo_whatsapp', $features)) {
                $features['modulo_whatsapp'] = false;
            }

            $plan->update(['features' => $features]);
        }
    }

    public function down(): void
    {
        // Mantém: remover apagaria a escolha feita na Central depois.
    }
};
