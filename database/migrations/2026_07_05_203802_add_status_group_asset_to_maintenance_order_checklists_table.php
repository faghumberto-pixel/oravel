<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * status: 3 estados reais (conforme/nao_conforme/nao_aplicavel), mesmo
     * vocabulario que o Repeater da aba "Vistoria/Checklist" do
     * MaintenanceOrderResource ja usa no form -- so nunca existiu como
     * coluna, entao a resposta do tecnico era descartada silenciosamente.
     * is_completed continua existindo, sem uso concorrente conhecido.
     *
     * checklist_group_id ja existia como uuid solto, sem FK -- nunca foi
     * conectado a nada real (o checklist_group_id de assets tinha sido
     * removido em 2026_05_09). Adiciona a FK de verdade agora.
     *
     * asset_id: itens extras de checklist especificos de UM ativo
     * (is_template=true, asset_id preenchido, checklist_group_id nulo),
     * paralelos ao basico do grupo.
     */
    public function up(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->string('status')->nullable()->after('is_completed');
            $table->foreign('checklist_group_id')->references('id')->on('checklist_groups')->nullOnDelete();
            $table->foreignUuid('asset_id')->nullable()->after('checklist_group_id')
                ->constrained('assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
            $table->dropForeign(['checklist_group_id']);
            $table->dropColumn('status');
        });
    }
};
