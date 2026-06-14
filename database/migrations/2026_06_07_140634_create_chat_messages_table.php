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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID conforme o Model ChatMessage
            $table->uuid('chat_room_id');  // Relacionamento com a sala
            $table->uuid('user_id');       // Relacionamento com o autor
            $table->text('message');       // Conteúdo da mensagem
            $table->uuid('tenant_id');     // Isolamento multitenant
            $table->timestamps();
            
            // Sugestão: adicione índices para performance nas consultas do GlobalChat
            $table->index('chat_room_id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};