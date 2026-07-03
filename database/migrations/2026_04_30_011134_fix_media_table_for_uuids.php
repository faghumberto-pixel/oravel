<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verifica se a tabela existe antes de tentar qualquer alteração
        if (Schema::hasTable('media')) {
            // SQLite usa sintaxe diferente de PostgreSQL para DROP INDEX
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite: sem aspas duplas, sem schema
                DB::statement('DROP INDEX IF EXISTS media_model_id_model_type_index');
            } else {
                // PostgreSQL / MySQL
                DB::statement('DROP INDEX IF EXISTS "media_model_id_model_type_index"');
            }

            // Schema alterations (se necessário)
            Schema::table('media', function (Blueprint $table) {
                // Aqui você coloca as alterações de colunas que a migration original pretendia
                // Exemplo: $table->uuid('model_id')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Se precisar reverter, recrie o índice se necessário
        if (Schema::hasTable('media')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite: recriar índice simples
                DB::statement('CREATE INDEX IF NOT EXISTS media_model_id_model_type_index ON media(model_id, model_type)');
            } else {
                // PostgreSQL / MySQL
                DB::statement('CREATE INDEX IF NOT EXISTS media_model_id_model_type_index ON media(model_id, model_type)');
            }
        }
    }
};