<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove qualquer resto de tentativa anterior para garantir um estado limpo
        Schema::dropIfExists('maintenance_orders');

        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relacionamento com Ativo (UUID)
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            
            // Status e Prioridade
            $table->string('status')->default('aberta');
            $table->string('priority')->default('media');
            
            // Descrição e Solução
            $table->text('description');
            $table->text('solution')->nullable();
            
            // Relacionamento com Usuário (Detectado como BigInt no seu sistema)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_orders');
    }
};