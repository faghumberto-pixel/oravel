<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F3: marcas de "ja avisei" (em risco / vencido / escalonado) por cronometro, para cada aviso sair uma vez so, e o % de escalonamento do tenant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_timers', function (Blueprint $table) {
            $table->timestamp('warned_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
        });

        Schema::table('sla_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('escalate_pct')->default(150);
        });
    }

    public function down(): void
    {
        Schema::table('sla_settings', function (Blueprint $table) {
            $table->dropColumn('escalate_pct');
        });
        Schema::table('sla_timers', function (Blueprint $table) {
            $table->dropColumn(['warned_at', 'breached_at', 'escalated_at']);
        });
    }
};
