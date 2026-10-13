<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * As migrations 2026_11_07_110000 e 2026_11_07_120000 gravaram "tabela_absences" e "modulo_app_colaborador" como
     * itens de lista ({"0":"tabela_absences"}) dentro do mapa de módulos do Contrato ({"chave":true}). O sistema
     * aceitava assim, mas a Central não reconhece esse formato: ao salvar o contrato, os dois módulos sumiriam.
     * Converte qualquer item de lista para chave => true e remove o item solto.
     */
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true);

            if (! is_array($features)) {
                continue;
            }

            $mudou = false;

            foreach ($features as $chave => $valor) {
                if (is_int($chave) && is_string($valor)) {
                    $features[$valor] = true;
                    unset($features[$chave]);
                    $mudou = true;
                }
            }

            if ($mudou) {
                DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
            }
        }
    }

    public function down(): void
    {
        // Sem volta: o formato antigo era um defeito.
    }
};
