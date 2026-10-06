<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laudos, notas fiscais, manuais e outros documentos do ativo (o arquivo em si fica na
        // media library, disco privado 'media_private').
        Schema::create('asset_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('titulo');
            $table->string('numero')->nullable();           // nº da nota fiscal / do laudo
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable();      // laudos e certificados vencem
            $table->decimal('valor', 12, 2)->nullable();    // valor da nota fiscal
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');
    }
};
