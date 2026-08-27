<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens do catalogo global existem justamente pra virar OS sozinhos quando
 * vencem -- default true aqui, ao contrario de MaintenancePlan.auto_create_order
 * (default false, planos manuais continuam opt-in). Ver
 * MaintenancePlan::importFromFamilyTemplate().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pmp_template_items', function (Blueprint $table) {
            $table->boolean('auto_create_order')->default(true)->after('is_critical');
        });
    }

    public function down(): void
    {
        Schema::table('pmp_template_items', function (Blueprint $table) {
            $table->dropColumn('auto_create_order');
        });
    }
};
