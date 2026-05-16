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
            $table->id();
            
            // Relacionamentos Estruturais
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('chat_room_id');
            
            // Conteúdo do Diálogo (Texto cru ou Payload estruturado de arquivos)
            $table->text('message');
            
            // Vínculos de Negócio e Rastreabilidade do Pátio Oravel
            $table->string('context_type')->default('geral'); // 'os', 'material', 'gerencia', 'aviso', 'geral'
            $table->string('context_id')->nullable();         // Código ou número da O.S. / Pedido
            
            // Controle de Compartilhamento e Encaminhamento
            $table->boolean('is_forwarded')->default(false);
            $table->string('forwarded_from_name')->nullable();
            
            $table->timestamps();

            // Amarração manual da chave estrangeira para o ID UUID da tabela chat_rooms
            $table->foreign('chat_room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
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