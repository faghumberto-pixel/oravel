<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_orders', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'finished_at')) {
                $table->timestamp('finished_at')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'technician_id')) {
                $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('maintenance_orders', 'workflow_status')) {
                $table->string('workflow_status')->default('pending');
            }
        });
    }

    public function down(): void
    {
        // Aqui você pode manter o dropColumn, mas se a coluna não existir, ele vai falhar.
        // Em produção real, apenas remova o down ou trate com hasColumn também.
    }
};