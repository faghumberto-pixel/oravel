<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Normalizar tabela model_has_permissions
        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_permissions', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable();
                $table->index('tenant_id');
            }
        });

        // 2. Normalizar tabela model_has_roles
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_roles', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable();
                $table->index('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
