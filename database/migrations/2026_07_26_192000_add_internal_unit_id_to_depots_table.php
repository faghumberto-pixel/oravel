<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ponte entre InternalUnit (matriz/filial, usado pelo estoque) e Depot
     * (origem de rota da frota, App\Services\RouteOptimizationService) --
     * ate' 2026-07-26 eram dois cadastros de endereco desconexos: uma
     * unidade com CEP preenchido nao virava origem valida de rota. Nullable
     * porque Depot avulso (sem unidade vinculada) continua sendo permitido.
     */
    public function up(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->foreignUuid('internal_unit_id')->nullable()->unique()->after('tenant_id')
                ->constrained('internal_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_unit_id');
        });
    }
};
