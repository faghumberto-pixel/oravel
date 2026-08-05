<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rascunho do e-mail de prospecção (D3 da cadência) personalizado pra
     * esse lead -- gerado manualmente ou por IA, editável, guardado aqui
     * pra sair junto na exportação de leads em vez de viver só numa
     * conversa avulsa. Ver SalesLeadResource/ListSalesLeads (botão Exportar).
     */
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->text('outreach_email_draft')->nullable()->after('oravel_solution');
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn('outreach_email_draft');
        });
    }
};
