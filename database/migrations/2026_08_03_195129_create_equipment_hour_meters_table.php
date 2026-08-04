<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de staging para o app mobile offline do técnico. Cada linha é
     * um apontamento capturado localmente no aparelho (com client_uuid
     * gerado no device pra permitir upsert idempotente quando o lote for
     * reenviado por falha de rede) e sincronizado em lote pelo
     * HourMeterSyncController. Ao processar com sucesso, cada linha gera um
     * App\Models\HorimeterReading real (mesma tabela usada pelo apontamento
     * desktop/dossiê), reaproveitando o HorimeterReadingObserver pra regra
     * de bloqueio de reset/salto -- esta tabela não duplica essa lógica.
     */
    public function up(): void
    {
        Schema::create('equipment_hour_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('horimeter_reading_id')->nullable()->constrained('horimeter_readings')->nullOnDelete();

            $table->uuid('client_uuid')->comment('Gerado no device para deduplicar reenvios do mesmo lote offline');
            $table->decimal('reading', 10, 2);
            $table->string('photo_path')->nullable();
            $table->timestamp('recorded_at')->comment('Data/hora do sistema no momento da captura em campo');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('reset_confirmed')->default(false);

            $table->string('sync_status')->default('pending')->comment('pending|synced|failed');
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'client_uuid']);
            $table->index(['asset_id', 'recorded_at']);
            $table->index(['tenant_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_hour_meters');
    }
};
