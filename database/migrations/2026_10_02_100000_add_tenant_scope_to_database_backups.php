<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backups por cliente: cada arquivo guarda os dados de UM tenant (kind=tenant), ou o que é da
        // plataforma (kind=platform). kind=full são os dumps gerais antigos (anteriores a 02/10/2026).
        Schema::table('database_backups', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('kind', 20)->default('full');
            $table->unsignedBigInteger('rows_count')->default(0);
            $table->string('sha256', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('database_backups', function (Blueprint $table) {
            $table->dropColumn(['tenant_id', 'kind', 'rows_count', 'sha256']);
        });
    }
};
