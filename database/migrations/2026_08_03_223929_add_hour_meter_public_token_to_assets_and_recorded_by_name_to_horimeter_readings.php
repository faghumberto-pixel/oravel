<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * hour_meter_public_token: habilita o link publico (sem login) de
     * registro de horimetro pra funcionario do cliente locatario -- gerado
     * sob demanda (nao no creating(), Asset ja tem registros existentes),
     * ver Asset::hourMeterPublicToken(). recorded_by_name: quem registrou
     * por esse link (texto livre, nao ha User pra essa pessoa), ver
     * HorimeterReading::isExternalClientSource()/originLabel().
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->uuid('hour_meter_public_token')->nullable()->unique()->after('id');
        });

        Schema::table('horimeter_readings', function (Blueprint $table) {
            $table->string('recorded_by_name')->nullable()->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('hour_meter_public_token');
        });

        Schema::table('horimeter_readings', function (Blueprint $table) {
            $table->dropColumn('recorded_by_name');
        });
    }
};
