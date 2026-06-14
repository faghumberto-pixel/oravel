<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Evita erro caso a tabela já exista (ambiente já provisionado)
        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();

                // Multitenancy por coluna - isolamento via Global Scope
                $table->foreignId('tenant_id')
                    ->constrained()
                    ->cascadeOnDelete();

                // Remetente e destinatário (sempre dentro do mesmo tenant)
                $table->foreignId('sender_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId('receiver_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                // Conteúdo da mensagem
                $table->text('body')->nullable();

                // Anexos (imagem e áudio gravado)
                $table->string('attachment_path')->nullable();
                $table->string('audio_path')->nullable();

                // Controle de leitura (útil para badges/notificações)
                $table->timestamp('read_at')->nullable();

                $table->timestamps();

                // Índices para otimizar a busca de conversas
                $table->index(['tenant_id', 'sender_id', 'receiver_id']);
                $table->index(['tenant_id', 'receiver_id', 'read_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
