<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** G1 fase 7: cronometro de SLA pode nascer do catalogo (grupo x criticidade) e ter as proprias pausas. Aditivo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_timers', function (Blueprint $table) {
            $table->uuid('catalogo_sla_id')->nullable()->after('policy_id');
            $table->jsonb('pause_statuses')->nullable()->after('calendar_mode');
        });
    }

    public function down(): void
    {
        Schema::table('sla_timers', function (Blueprint $table) {
            $table->dropColumn(['catalogo_sla_id', 'pause_statuses']);
        });
    }
};
