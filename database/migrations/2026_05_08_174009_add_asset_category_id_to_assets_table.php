<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Conversas
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // CORREÇÃO: Usando foreignUuid para bater com o Tenant
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->string('title')->nullable();
            $table->timestamps();
        });

        // Tabela de Mensagens
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        
        // Tabela Pivot de Participantes (se houver no seu arquivo)
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};