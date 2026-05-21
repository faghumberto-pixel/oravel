<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Verifica se a coluna já existe antes de adicionar
            if (!Schema::hasColumn('maintenance_orders', 'technician_signature')) {
                $table->text('technician_signature')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'client_signature')) {
                $table->text('client_signature')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_orders', 'technician_signature')) {
                $table->dropColumn('technician_signature');
            }
            if (Schema::hasColumn('maintenance_orders', 'client_signature')) {
                $table->dropColumn('client_signature');
            }
        });
    }
};