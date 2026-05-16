<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Ajuste na WorkOrders (MaintenanceOrders)
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->string('internal_status')->default('aguardando_diagnostico');
        });

        // Tabela de Pedidos de Peças (Suprimentos)
        Schema::create('parts_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('maintenance_order_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('material_id')->constrained();
            $table->integer('quantity');
            $table->enum('status', ['pendente', 'entregue'])->default('pendente');
            $table->decimal('cost_at_time', 15, 2)->nullable();
            $table->timestamps();
        });

        // Tabela de Fila de Logística
        Schema::create('logistics_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('maintenance_order_id')->constrained();
            $table->foreignUuid('asset_id')->constrained();
            $table->enum('type', ['entrega', 'retirada']);
            $table->enum('status', ['aguardando', 'transito', 'concluido'])->default('aguardando');
            $table->string('destination')->nullable();
            $table->timestamps();
        });

        // Tabela de Disponibilidade Comercial
        Schema::create('fleet_status', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('asset_id')->unique()->constrained();
            $table->boolean('is_available')->default(false);
            $table->string('capacity_label')->nullable(); // Ex: 500kVA
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('fleet_status');
        Schema::dropIfExists('logistics_queue');
        Schema::dropIfExists('parts_requests');
    }
};
