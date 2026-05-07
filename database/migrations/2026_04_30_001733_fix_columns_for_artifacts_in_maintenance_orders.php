<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Altera as colunas para o tipo TEXT para suportar assinaturas em Base64
     * e o array JSON da galeria de fotos.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Mudamos para text para garantir que dados longos não sejam truncados
            $table->text('photo_path')->nullable()->change();
            $table->text('signature_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     * Retorna ao estado original (string curta) se necessário.
     */
    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->change();
            $table->string('signature_path', 255)->nullable()->change();
        });
    }
};