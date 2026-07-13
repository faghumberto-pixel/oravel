<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ate aqui, o historico de status gravava so quem EXECUTOU a transferencia
 * (user_id), nao pra qual tecnico a OS foi transferida -- impossivel saber
 * quanto tempo cada tecnico realmente trabalhou numa OS que passou por
 * mais de uma mao. Estes 3 campos, preenchidos so pela acao "Transferir"
 * (EditMaintenanceOrder.php), fecham essa lacuna dai pra frente: quem tinha
 * a OS antes, pra quem foi, e quantos segundos do cronometro
 * (total_time_seconds) pertencem a esse segmento que acabou de fechar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_status_histories', function (Blueprint $table) {
            $table->foreignUuid('old_technician_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('new_technician_id')->nullable()->after('old_technician_id')->constrained('users')->nullOnDelete();
            $table->integer('segment_seconds')->nullable()->after('new_technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_status_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('old_technician_id');
            $table->dropConstrainedForeignId('new_technician_id');
            $table->dropColumn('segment_seconds');
        });
    }
};
