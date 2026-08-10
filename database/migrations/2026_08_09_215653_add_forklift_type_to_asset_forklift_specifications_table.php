<?php

use App\Domain\Fleet\Models\ForkliftSpecification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classe II/III de empilhadeira nao existia como conceito em lugar
     * nenhum do schema -- so' o guarda-chuva "Empilhadeira" (AssetCategory)
     * + atributos soltos como mast_type. forklift_type decide tanto a
     * classe quanto quais campos tecnicos fazem sentido no form
     * (AssetResource). Backfill: os ativos ja cadastrados (seeder
     * EmpilhadeirasDemoSeeder) inferem o subtipo a partir do mast_type que
     * ja tinham, pra nao nascer com NULL silencioso.
     */
    public function up(): void
    {
        Schema::table('asset_forklift_specifications', function (Blueprint $table) {
            $table->string('forklift_type')->nullable()->after('asset_id');
        });

        DB::table('asset_forklift_specifications')
            ->where('mast_type', 'retratil')
            ->update(['forklift_type' => ForkliftSpecification::TYPE_RETRATIL]);

        DB::table('asset_forklift_specifications')
            ->whereNull('forklift_type')
            ->update(['forklift_type' => ForkliftSpecification::TYPE_CONTRABALANCADA_ELETRICA]);
    }

    public function down(): void
    {
        Schema::table('asset_forklift_specifications', function (Blueprint $table) {
            $table->dropColumn('forklift_type');
        });
    }
};
