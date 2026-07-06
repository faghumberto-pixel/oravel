<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_maintenance_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('horimetro_at_execution', 12, 2);
            $table->decimal('next_due_horimetro', 12, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_executions');
    }
};
