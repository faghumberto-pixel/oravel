<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordem em que os dashboards da empresa aparecem (abas do Início e da página "Dashboards").
     * Antes a ordem era alfabética; as atribuições que já existem mantêm a ordem em que foram criadas.
     */
    public function up(): void
    {
        Schema::table('dashboard_assignments', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('is_home');
        });

        $posicao = [];
        foreach (DB::table('dashboard_assignments')->orderBy('created_at')->orderBy('id')->get(['id', 'tenant_id']) as $a) {
            $posicao[$a->tenant_id] = ($posicao[$a->tenant_id] ?? 0) + 1;
            DB::table('dashboard_assignments')->where('id', $a->id)->update(['position' => $posicao[$a->tenant_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('dashboard_assignments', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
