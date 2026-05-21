<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Lista de tabelas do Spatie que possuem a coluna tenant_id
        $tables = ['roles', 'model_has_roles', 'role_has_permissions', 'model_has_permissions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                // 🔥 SOLUÇÃO DEFINITIVA EM POSTGRESQL: Altera o tipo da coluna fisicamente para UUID
                // usando USING para forçar a conversão de dados sem quebrar
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