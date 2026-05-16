<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_requests', function (Blueprint $table) {
            $table->uuid('id')->primary(); // ID da solicitação como UUID
            
            // Relacionamentos com UUID (OS e Tenant)
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignUuid('maintenance_order_id')->constrained('maintenance_orders')->onDelete('cascade');
            
            // Relacionamento com Usuário (ID Numérico / BigInt)
            $table->foreignId('user_id')->constrained('users');

            $table->string('part_description');
            $table->integer('quantity')->default(1);
            $table->string('status')->default('pendente'); // pendente, pedida, entregue
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_requests');
    }
};