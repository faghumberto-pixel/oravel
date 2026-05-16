<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Vinculo do Tenant (UUID)
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->string('type'); // 'direct', 'group', 'os'
            $table->string('title')->nullable(); // Nome do grupo ou tema
            
            // CORREÇÃO: Mudado para foreignUuid para casar perfeitamente com a tabela de Ordens de Serviço
            $table->foreignUuid('maintenance_order_id')->nullable()->constrained()->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};