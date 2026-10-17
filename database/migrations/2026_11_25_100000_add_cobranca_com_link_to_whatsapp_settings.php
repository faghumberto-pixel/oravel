<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Aviso de cobrança por WhatsApp com o link de pagamento (modelo Meta de 5 variáveis: nome, descrição, valor, vencimento, link). */
    public function up(): void
    {
        Schema::table('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->boolean('cobranca_com_link')->default(false)->after('template_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_whatsapp_settings', fn (Blueprint $t) => $t->dropColumn('cobranca_com_link'));
    }
};
