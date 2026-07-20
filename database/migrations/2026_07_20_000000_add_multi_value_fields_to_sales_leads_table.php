<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * decision_maker_name/role viram decision_makers (JSON, lista de
     * {name, role}) -- pedido explicito do usuario pra poder ter mais de
     * um tomador de decisao por lead. additional_segments/additional_sources
     * sao listas complementares: segment/source continuam como valor
     * PRIMARIO (unico) porque dashboards/mapa/Kanban ja dependem de um
     * valor so' por lead -- as listas novas sao so' pra registrar
     * segmentos/origens adicionais, sem quebrar nada que ja usa o valor
     * unico existente.
     */
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->json('decision_makers')->nullable()->after('decision_maker_role');
            $table->json('additional_segments')->nullable()->after('segment');
            $table->json('additional_sources')->nullable()->after('source');
        });

        DB::table('sales_leads')
            ->whereNotNull('decision_maker_name')
            ->orWhereNotNull('decision_maker_role')
            ->orderBy('id')
            ->each(function ($lead) {
                DB::table('sales_leads')->where('id', $lead->id)->update([
                    'decision_makers' => json_encode([[
                        'name' => $lead->decision_maker_name,
                        'role' => $lead->decision_maker_role,
                    ]]),
                ]);
            });

        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn(['decision_maker_name', 'decision_maker_role']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->string('decision_maker_name')->nullable();
            $table->string('decision_maker_role')->nullable();
        });

        DB::table('sales_leads')->whereNotNull('decision_makers')->orderBy('id')->each(function ($lead) {
            $first = json_decode($lead->decision_makers, true)[0] ?? null;

            if ($first) {
                DB::table('sales_leads')->where('id', $lead->id)->update([
                    'decision_maker_name' => $first['name'] ?? null,
                    'decision_maker_role' => $first['role'] ?? null,
                ]);
            }
        });

        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn(['decision_makers', 'additional_segments', 'additional_sources']);
        });
    }
};
