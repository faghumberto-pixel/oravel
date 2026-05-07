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
            // Usa o comando nativo do PostgreSQL para dropar o índice com segurança
            // Isso ignora o erro caso o índice não exista
            DB::statement('DROP INDEX IF EXISTS "media_model_id_model_type_index"');
            
            // Prossiga com o restante da lógica que você pretendia para a tabela media
            Schema::table('media', function (Blueprint $table) {
                // Aqui você coloca as alterações de colunas que a migration original pretendia
                // Exemplo hipotético: $table->uuid('model_id')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Se precisar reverter, recrie o índice se necessário
    }
};