<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposta_comercial_templates', function (Blueprint $table) {
            $table->text('cabecalho')->nullable();
            $table->json('campos')->nullable();
            $table->string('imagem_referencia')->nullable();
        });

        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->text('cabecalho')->nullable();
            $table->json('campos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proposta_comercial_templates', function (Blueprint $table) {
            $table->dropColumn(['cabecalho', 'campos', 'imagem_referencia']);
        });

        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->dropColumn(['cabecalho', 'campos']);
        });
    }
};
