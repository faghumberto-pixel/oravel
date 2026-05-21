<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Correção para a tabela: model_has_permissions
        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_permissions', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable();
                $table->index(['tenant_id', 'model_id', 'model_type'], 'model_has_permissions_tenant_index');
            }
        });

        // 2. Correção para a tabela: model_has_roles
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_roles', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable();
                $table->index(['tenant_id', 'model_id', 'model_type'], 'model_has_roles_tenant_index');
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

        Schema::table('model_has_roles', function (Blueprint $table) {
            if (Schema::hasColumn('model_has_roles', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};