<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarentena real (locadoras de evento): has_damage por item do Laudo de
 * Recebimento (sem isso, nao tinha como saber se um item achou avaria --
 * so tinha 'notes' livre) + quarantine_released_at/by no cabecalho, pra
 * registrar a liberacao manual (ver EquipmentPatioArrivalMobile::finalize()
 * e o novo botao "Liberar da Quarentena" em AssetResource).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_patio_arrival_items', function (Blueprint $table) {
            $table->boolean('has_damage')->default(false);
        });

        Schema::table('equipment_patio_arrivals', function (Blueprint $table) {
            $table->timestamp('quarantine_released_at')->nullable();
            $table->foreignUuid('quarantine_released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_patio_arrival_items', function (Blueprint $table) {
            $table->dropColumn('has_damage');
        });

        Schema::table('equipment_patio_arrivals', function (Blueprint $table) {
            $table->dropColumn('quarantine_released_at');
            $table->dropConstrainedForeignId('quarantine_released_by_user_id');
        });
    }
};
