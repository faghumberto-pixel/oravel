<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site e e-mail institucional como opção do Contrato (plans): 'nao' | 'incluso' (gratuito no contrato de 12 meses,
     * renova com o contrato) | 'avulso' (vendido à parte, valor único). Quando ligado, o Anexo II do contrato assinado o descreve.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('site_email_modo', 20)->default('nao');
            $table->decimal('site_email_valor', 10, 2)->nullable();
            $table->boolean('site_email_cliente_possui')->default(false);
            $table->text('site_email_escopo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['site_email_modo', 'site_email_valor', 'site_email_cliente_possui', 'site_email_escopo']);
        });
    }
};
