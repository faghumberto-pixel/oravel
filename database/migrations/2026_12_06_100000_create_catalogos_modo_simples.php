<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modo simples (Manutenção/Serviços): tipos de ação que a própria empresa cadastra e marca em cada OS; problemas comuns
     * desativáveis (nunca excluídos); chave por empresa para trocar entre interface simples e completa (nulo = automático pelo produto).
     */
    public function up(): void
    {
        Schema::create('maintenance_action_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('maintenance_order_action_type', function (Blueprint $table) {
            $table->uuid('maintenance_order_id');
            $table->uuid('maintenance_action_type_id');
            $table->primary(['maintenance_order_id', 'maintenance_action_type_id'], 'mo_action_type_primary');
            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete();
            $table->foreign('maintenance_action_type_id')->references('id')->on('maintenance_action_types')->cascadeOnDelete();
        });

        Schema::table('reported_problems', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('interface_simples')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('interface_simples'));
        Schema::table('reported_problems', fn (Blueprint $t) => $t->dropColumn('is_active'));
        Schema::dropIfExists('maintenance_order_action_type');
        Schema::dropIfExists('maintenance_action_types');
    }
};
