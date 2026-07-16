<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Gerente comercial ve todos os leads" nao e' uma role com nome fixo --
 * e' um privilegio que o admin do tenant liga em QUALQUER perfil que ele
 * ja tem ou cria (Gerente, Encarregado, Lider, etc). Mesmo padrao ja
 * usado por roles.department_id (Setor Supervisionado, Programacao).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'sees_all_crm_leads')) {
                $table->boolean('sees_all_crm_leads')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'sees_all_crm_leads')) {
                $table->dropColumn('sees_all_crm_leads');
            }
        });
    }
};
