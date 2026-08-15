<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ponto eletronico do colaborador de campo -- mesmo padrao de
     * equipment_hour_meters (staging offline do device, client_uuid gerado
     * no aparelho pra upsert idempotente quando o lote for reenviado por
     * falha de rede). recorded_at e' a hora do SERVIDOR no momento em que o
     * lote chega, nao a hora do dispositivo (que pode estar errada/
     * manipulada) -- device_recorded_at guarda a hora local só como
     * evidência bruta, nunca como fonte de verdade pra cálculo de jornada.
     */
    public function up(): void
    {
        Schema::create('time_clocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();

            $table->uuid('client_uuid')->comment('Gerado no device para deduplicar reenvios do mesmo lote offline');
            $table->string('tipo')->comment('entrada|saida|inicio_intervalo|fim_intervalo');
            $table->timestamp('recorded_at')->comment('Hora do SERVIDOR no momento do sync -- fonte de verdade');
            $table->timestamp('device_recorded_at')->comment('Hora local do device no momento da captura -- evidência bruta, não fonte de verdade');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('sync_status')->default('pending')->comment('pending|synced|failed');
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'client_uuid']);
            $table->index(['employee_id', 'recorded_at']);
            $table->index(['tenant_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_clocks');
    }
};
