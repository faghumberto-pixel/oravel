<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_communications', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Mantemos UUID aqui
            
            // Ajuste crucial: se a tabela users usa BigInt, aqui deve ser BigInt
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Mantemos a relação com a OS como UUID, pois ela utiliza HasUuids
            $table->foreignUuid('maintenance_order_id')->constrained('maintenance_orders')->onDelete('cascade');
            
            // Tenant também usa UUID, mantemos como está
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_communications');
    }
};