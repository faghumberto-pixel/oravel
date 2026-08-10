<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * load_bank_tested/load_bank_notes (2026-07-17) so' tinham boolean +
     * texto livre -- gap apontado no diagnostico de Geradores de Energia:
     * sem estrutura pra nivel de carga aplicado, duracao do teste ou
     * temperatura, que sao os dados reais que uma locadora de eventos
     * precisa registrar num teste de banco de carga.
     */
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->unsignedInteger('load_bank_percentage')->nullable()->after('load_bank_notes');
            $table->unsignedInteger('load_bank_duration_minutes')->nullable()->after('load_bank_percentage');
            $table->decimal('load_bank_temperature_c', 5, 2)->nullable()->after('load_bank_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropColumn(['load_bank_percentage', 'load_bank_duration_minutes', 'load_bank_temperature_c']);
        });
    }
};
