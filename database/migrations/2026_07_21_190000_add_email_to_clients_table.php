<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item 8 da auditoria POP: Client só tinha email_financial/
     * email_purchasing (setoriais) -- faltava um e-mail de contato geral
     * pra usar como destinatário padrão em qualquer comunicação (ex:
     * envio de orçamento, QuoteResource\Pages\EditQuote::enviar).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
