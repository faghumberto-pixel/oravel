<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** NFS-e (9C): modelo de WhatsApp para enviar a nota ao cliente e opção de enviar por e-mail assim que a nota é autorizada. */
    public function up(): void
    {
        Schema::table('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->string('template_nfse')->nullable(); // 3 variáveis: nome, número da nota, link da nota
        });

        Schema::table('tenant_fiscal_settings', function (Blueprint $table) {
            $table->boolean('enviar_email_ao_autorizar')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_whatsapp_settings', fn (Blueprint $t) => $t->dropColumn('template_nfse'));
        Schema::table('tenant_fiscal_settings', fn (Blueprint $t) => $t->dropColumn('enviar_email_ao_autorizar'));
    }
};
