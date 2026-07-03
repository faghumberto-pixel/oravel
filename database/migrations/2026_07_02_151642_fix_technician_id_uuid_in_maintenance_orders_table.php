<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('technician_id');
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->uuid('technician_id')->nullable()->after('assigned_technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('technician_id');
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('technician_id')->nullable();
        });
    }
};
