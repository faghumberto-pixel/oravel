<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nivel hierarquico (Tecnico=1 ... Gerente=6) atribuivel a qualquer Role,
 * junto com o department_id ja existente -- mesmo padrao aditivo de
 * "privilegio anexavel a qualquer perfil" ja usado por department_id e
 * sees_all_crm_leads. Nullable: papeis existentes (admin, Comercial etc)
 * continuam sem nivel, sem forcar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'hierarchy_level')) {
                $table->unsignedTinyInteger('hierarchy_level')->nullable()->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'hierarchy_level')) {
                $table->dropColumn('hierarchy_level');
            }
        });
    }
};
