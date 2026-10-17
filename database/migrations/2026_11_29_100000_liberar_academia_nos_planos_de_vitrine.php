<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A Academia agora faz parte da base de todos os pacotes. Os planos de vitrine já criados (que levam os módulos do pacote) ganham
     * o módulo; contratos de clientes não são alterados (a Central usa "Aplicar módulos do pacote" quando quiser).
     */
    public function up(): void
    {
        foreach (DB::table('plans')->where('campaign_tag', 'vitrine')->get() as $plano) {
            $features = json_decode($plano->features ?? '[]', true) ?? [];

            if (($features['tabela_courses'] ?? false) === true) {
                continue;
            }

            $features['tabela_courses'] = true;
            DB::table('plans')->where('id', $plano->id)->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        // Sem volta: não dá para saber se o módulo foi ligado por esta migration ou à mão.
    }
};
