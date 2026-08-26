<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat bidirecional Tenant<->Client. Sem tabela de "sala" (diferente do
 * Chat Interno via ChatRoom) -- a relação é sempre 1:1 por natureza, um
 * Client conversa só com o Tenant que o atende. sender_type/sender_id
 * resolve manualmente quem mandou (só 2 tipos possíveis: 'client' ou
 * 'user'), mais simples que um morphs() completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('sender_type'); // 'client' | 'user'
            $table->uuid('sender_id');
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_messages');
    }
};
