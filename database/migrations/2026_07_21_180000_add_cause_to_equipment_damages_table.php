<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Causa estruturada da avaria (desgaste_natural/mau_uso/dano_cliente)
     * -- antes so' existia como texto livre embutido em "description"
     * ("Mau uso detectado na OS #X"), sem dar pra filtrar/reportar por
     * causa de verdade (POP 6 da auditoria). Nullable de proposito: fica
     * "nao classificado" ate' o supervisor confirmar durante a revisao
     * (mesmo estagio de aguardando_supervisor), exceto no unico caminho
     * que ja sabe a causa com certeza no momento do registro (checklist
     * com severity='mau_uso' explicitamente marcado).
     */
    public function up(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->string('cause')->nullable()->after('damage_type');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->dropColumn('cause');
        });
    }
};
