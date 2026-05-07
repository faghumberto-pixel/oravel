<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Verifica se a coluna já existe para evitar erro
            if (!Schema::hasColumn('maintenance_orders', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('maintenance_orders', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['resolved_by', 'resolved_at']);
        });
    }
};