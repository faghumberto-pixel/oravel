<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_statuses', function (Blueprint $table) {
            // Adiciona a coluna tenant_id como UUID para o PostgreSQL
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_statuses', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};