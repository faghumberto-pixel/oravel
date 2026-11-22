<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * As telas novas do Intelligence (Diagnóstico, Comparação interna, Plano de evolução, Frota/finanças/oficina) chegam desligadas
     * (saas:sync-modules acrescenta chave nova como false). Liga SÓ nos contratos que já têm o produto Intelligence (pacote ou
     * produtos contratados); nenhum outro contrato é tocado. Formato mapa (chave => true), o mesmo que a Central grava.
     */
    private const CHAVES = ['menu_intelligence_diagnostico', 'menu_intelligence_comparacao', 'menu_intelligence_plano', 'menu_intelligence_operacao'];

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
            foreach (self::CHAVES as $chave) {
                $features[$chave] = true;
            }
            DB::table('plans')->where('id', $plano->id)->update(['features' => json_encode($features, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // Nada a desfazer: ligar uma tela a mais num contrato do Intelligence não é algo que se reverta em massa.
    }
};
