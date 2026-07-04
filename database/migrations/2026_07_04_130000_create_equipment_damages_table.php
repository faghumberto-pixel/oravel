<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_damages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_movement_item_id')->nullable()->constrained();
            $table->foreignUuid('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained();
            $table->foreignUuid('reported_by_user_id')->constrained('users');
            $table->string('severity');
            $table->text('description');
            $table->boolean('requires_replacement')->default(false);
            $table->foreignUuid('replacement_asset_id')->nullable()->constrained('assets');
            $table->string('status')->default('aguardando_assinatura_cliente');
            $table->text('client_signature')->nullable();
            $table->timestamp('client_acknowledged_at')->nullable();
            $table->foreignUuid('supervisor_reviewed_by')->nullable()->constrained('users');
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->foreignUuid('commercial_reviewed_by')->nullable()->constrained('users');
            $table->timestamp('commercial_reviewed_at')->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_damages');
    }
};
