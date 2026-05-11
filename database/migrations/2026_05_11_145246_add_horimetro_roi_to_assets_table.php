<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Horímetro que a máquina tinha ao ser adquirida (Ponto Zero para ROI)
            $table->decimal('horimetro_inicial', 12, 2)->default(0)->after('status');
            // Horímetro atualizado automaticamente pelo sistema via OS
            $table->decimal('last_horimetro', 12, 2)->default(0)->after('horimetro_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['horimetro_inicial', 'last_horimetro']);
        });
    }
};