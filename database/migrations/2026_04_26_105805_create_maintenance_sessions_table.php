<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('maintenance_order_id')->constrained('maintenance_orders')->onDelete('cascade');
            
            // CORREÇÃO: Usamos foreignId para criar uma coluna bigint 
            // que é compatível com o id da sua tabela 'users'
            $table->foreignId('user_id')->constrained('users'); 
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_sessions');
    }
};