<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        // Adiciona tenant_id na tabela pivot de permissões do modelo
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            if (!Schema::hasColumn(config('permission.table_names.model_has_permissions'), 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            }
        });

        // Adiciona tenant_id na tabela pivot de roles do modelo
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            if (!Schema::hasColumn(config('permission.table_names.model_has_roles'), 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};