<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default true de proposito: protege usuarios ja existentes
            // (e qualquer criacao direta via seeder/TenantProvisioner/
            // UserResource) de ficar bloqueado retroativamente. So o
            // auto-cadastro (Login::register()) seta false explicitamente.
            $table->boolean('is_approved')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });
    }
};
