<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_has_permissions')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('model_has_permissions', 'tenant_id')) {
                    // Adiciona o tenant_id como UUID aceitando nulo (para compatibilidade com super-admins)
                    $table->uuid('tenant_id')->nullable();
                    
                    // Cria o índice composto para otimizar as consultas de ACL do Postgres
                    $table->index(['tenant_id', 'model_id', 'model_type'], 'model_has_permissions_tenant_model_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('model_has_permissions')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                if (Schema::hasColumn('model_has_permissions', 'tenant_id')) {
                    $table->dropIndex('model_has_permissions_tenant_model_index');
                    $table->dropColumn('tenant_id');
                }
            });
        }
    }
};