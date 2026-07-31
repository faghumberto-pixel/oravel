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
        Schema::table('asset_movements', function (Blueprint $table) {
            // Tipo de movimentação
            $table->enum('movement_type', ['general', 'mobilization', 'demobilization'])->default('general')->after('reason');

            // Técnico responsável
            $table->foreignUuid('technician_id')->nullable()->constrained('users', 'id')->nullOnDelete()->after('movement_type');

            // Contrato associado (para mobilizações/desmobilizações em locação)
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete()->after('technician_id');

            // Dados capturados na mobilização/desmobilização
            $table->json('checklist_items')->nullable()->comment('Items e seus estados')->after('contract_id');
            $table->json('equipment_photos_before')->nullable()->comment('UUIDs de fotos do início')->after('checklist_items');
            $table->json('equipment_photos_after')->nullable()->comment('UUIDs de fotos do retorno')->after('equipment_photos_before');

            // Leituras de horímetro e combustível
            $table->decimal('horimeter_reading_start', 10, 2)->nullable()->after('equipment_photos_after');
            $table->decimal('horimeter_reading_end', 10, 2)->nullable()->after('horimeter_reading_start');
            $table->enum('fuel_level_start', ['empty', 'half', 'full'])->nullable()->after('horimeter_reading_end');
            $table->enum('fuel_level_end', ['empty', 'half', 'full'])->nullable()->after('fuel_level_start');

            // Observações e notas de voz
            $table->text('observations')->nullable()->after('fuel_level_end');
            $table->text('voice_notes')->nullable()->comment('Transcrição (processada depois)')->after('observations');

            // Assinatura digital
            $table->longText('signature_data')->nullable()->comment('Base64 da assinatura')->after('voice_notes');

            // Divergências encontradas (desmobilização)
            $table->json('divergences')->nullable()->comment('Items faltantes, danificados, etc')->after('signature_data');

            // Estado de sincronização (offline-first)
            $table->enum('sync_status', ['pending', 'syncing', 'synced', 'conflict'])->default('synced')->after('divergences');
            $table->timestamp('synced_at')->nullable()->after('sync_status');

            // Conclusão
            $table->timestamp('completed_at')->nullable()->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->dropColumn([
                'movement_type', 'technician_id', 'contract_id',
                'checklist_items', 'equipment_photos_before', 'equipment_photos_after',
                'horimeter_reading_start', 'horimeter_reading_end',
                'fuel_level_start', 'fuel_level_end',
                'observations', 'voice_notes', 'signature_data',
                'divergences', 'sync_status', 'synced_at', 'completed_at'
            ]);
        });
    }
};
