<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MaintenanceOrder ja tinha 'branch_id' no $fillable e um metodo
     * branch(): BelongsTo (app/Models/MaintenanceOrder.php) apontando pra
     * essa coluna -- mas a coluna nunca existiu de fato na tabela (achado
     * durante a populacao de dados do dashboard Gestao a Vista, cujo
     * filtro de Unidade depende dela). GestaoAVistaService::baseQuery() ja
     * tem um ->when($filtros['branchId'] ?? null, ...) protegendo contra
     * o filtro nao ser usado, entao nada quebrava ate agora -- so nunca
     * havia dado real pra filtrar.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_orders', 'branch_id')) {
                $table->foreignUuid('branch_id')->nullable()->after('asset_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_orders', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};
