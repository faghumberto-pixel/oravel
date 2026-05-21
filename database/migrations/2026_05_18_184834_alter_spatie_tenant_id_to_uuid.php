<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelas do Spatie que precisam aceitar os UUIDs do Oravel
        $tables = ['roles', 'model_has_roles', 'role_has_permissions', 'model_has_permissions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                // 🛠️ ALTERAÇÃO FÍSICA NO POSTGRES: Transforma a coluna em UUID real
                DB::statement("ALTER TABLE {$table} ALTER COLUMN tenant_id TYPE UUID USING tenant_id::text::uuid");
            }
        }
    }

    public function down(): void
    {
        $tables = ['roles', 'model_has_roles', 'role_has_permissions', 'model_has_permissions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN tenant_id TYPE BIGINT USING NULL");
            }
        }
    }
};