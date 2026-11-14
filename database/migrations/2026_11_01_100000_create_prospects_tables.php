<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('cnpj', 20)->unique();
            $t->string('nome', 200);
            $t->string('razao_social', 255)->nullable();
            $t->string('cidade', 120)->nullable();
            $t->string('porte', 20)->nullable();
            $t->unsignedBigInteger('capital')->nullable();
            $t->unsignedSmallInteger('ano_abertura')->nullable();
            $t->string('atividade', 160)->nullable();
            $t->string('telefone', 40)->nullable();
            $t->string('email', 200)->nullable();
            $t->string('socio', 200)->nullable();
            $t->string('socio_cargo', 120)->nullable();
            $t->unsignedTinyInteger('nota')->default(0);
            $t->boolean('regiao_campinas')->default(false);
            $t->boolean('empresa_nova')->default(false);
            $t->unsignedSmallInteger('lote')->nullable();
            $t->string('status', 20)->default('nao_enviado');
            $t->date('proximo_contato')->nullable();
            $t->timestamp('ultimo_contato_em')->nullable();
            $t->text('observacoes')->nullable();
            $t->string('origem', 80)->nullable();
            $t->timestamps();
            $t->index(['status', 'lote']);
        });

        Schema::create('prospect_contatos', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $t->date('data');
            $t->string('canal', 20)->default('whatsapp');
            $t->string('titulo', 160)->nullable();
            $t->text('texto');
            $t->string('autor', 120)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_contatos');
        Schema::dropIfExists('prospects');
    }
};
