<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item 9 da auditoria POP: liga a Conta a Receber gerada quando um
     * App\Models\Quote é encaminhado ao Financeiro (POP 4) de volta ao
     * orçamento que a originou -- fila real do Financeiro passa a ser a
     * tela já existente "Contas a Receber" (AccountReceivableResource),
     * em vez de uma tela nova só pra isso.
     */
    public function up(): void
    {
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->foreignUuid('quote_id')->nullable()->after('billing_plan_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn('quote_id');
        });
    }
};
