<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 (SLA com varios relogios): politicas por prioridade (e tipo de OS), configuracao por tenant (status que pausam,
 * % de alerta) e cronometros genericos (hoje: Resposta, Atendimento e Resolucao da OS; depois, outros registros).
 * Tudo aditivo; sla_target_minutes continua existindo e vira o relogio "Atendimento" (legado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('priority_code', 40);
            $table->string('maintenance_type', 40)->nullable(); // nulo = todos os tipos
            $table->unsignedInteger('response_minutes')->nullable();
            $table->unsignedInteger('service_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'priority_code', 'is_active']);
        });

        Schema::create('sla_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('pause_statuses')->nullable();
            $table->unsignedSmallInteger('warn_pct')->default(70);
            $table->timestamps();
        });

        Schema::create('sla_timers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('clock', 20); // response | service | resolution
            $table->foreignUuid('policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->timestamp('started_at');
            $table->unsignedInteger('target_minutes');
            $table->timestamp('due_at');
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->timestamp('stopped_at')->nullable();
            $table->string('result', 12)->nullable(); // met | breached | cancelled
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'clock']);
            $table->index(['tenant_id', 'stopped_at', 'due_at']);
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->timestamp('first_response_at')->nullable();
        });

        // OS ja iniciadas: a resposta e' o inicio. As demais ficam nulas (nao recalculamos historico).
        DB::table('maintenance_orders')->whereNotNull('started_at')->update(['first_response_at' => DB::raw('started_at')]);
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('first_response_at');
        });
        Schema::dropIfExists('sla_timers');
        Schema::dropIfExists('sla_settings');
        Schema::dropIfExists('sla_policies');
    }
};
