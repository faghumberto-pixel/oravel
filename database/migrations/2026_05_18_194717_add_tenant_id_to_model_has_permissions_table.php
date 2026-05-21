<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_permissions', 'tenant_id')) {
                // 🛡️ ADICIONA A COLUNA COMO UUID NATIVO: Alinhado com o PostgreSQL do Oravel
                $table->uuid('tenant_id')->nullable();
                
                // Indexa o campo para garantir performance nas checagens de permissões
                $table->index(['tenant_id', 'model_id', 'model_type'], 'model_has_permissions_tenant_model_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('model_has_permissions', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};