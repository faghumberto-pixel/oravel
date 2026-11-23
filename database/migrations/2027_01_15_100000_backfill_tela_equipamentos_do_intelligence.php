<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A tela "Equipamentos" do Intelligence é uma chave nova de contrato e chega desligada (saas:sync-modules acrescenta chave nova
     * como false). Liga SÓ nos contratos que já têm o produto Intelligence; nenhum outro contrato é tocado. Formato mapa.
     */
    public function up(): void
    {
        foreach (DB::table('plans')->get(['id', 'pacote', 'pacotes', 'features']) as $plano) {
            $pacotes = json_decode((string) $plano->pacotes, true) ?: [];
            if ($plano->pacote !== 'intelligence' && ! in_array('intelligence', $pacotes, true)) {
                continue;
            }
            $features = json_decode((string) $plano->features, true) ?: [];
            if (array_is_list($features)) {
                $features = array_fill_keys($features, true);
            }
            $features['menu_intelligence_equipamentos'] = true;
            DB::table('plans')->where('id', $plano->id)->update(['features' => json_encode($features, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // Nada a desfazer em massa.
    }
};
