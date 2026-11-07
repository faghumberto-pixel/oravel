<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5: calendario de SLA. Por tenant: modo (24x7 ou horario comercial), fuso e janelas por dia da semana; feriados proprios.
 * Cada cronometro guarda o modo com que nasceu (mudar o calendario da empresa nao reescreve prazos em andamento) e,
 * ao parar, os minutos gastos no calendario dele (para o tempo medio nao contar noite e fim de semana).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_settings', function (Blueprint $table) {
            $table->string('calendar_mode', 12)->default('24x7');
            $table->string('timezone', 50)->default('America/Sao_Paulo');
            $table->json('business_hours')->nullable();
        });

        Schema::create('sla_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });

        Schema::table('sla_timers', function (Blueprint $table) {
            $table->string('calendar_mode', 12)->default('24x7');
            $table->unsignedInteger('spent_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sla_timers', function (Blueprint $table) {
            $table->dropColumn(['calendar_mode', 'spent_minutes']);
        });
        Schema::dropIfExists('sla_holidays');
        Schema::table('sla_settings', function (Blueprint $table) {
            $table->dropColumn(['calendar_mode', 'timezone', 'business_hours']);
        });
    }
};
