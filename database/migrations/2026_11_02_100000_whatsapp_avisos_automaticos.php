<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->boolean('aviso_cobranca')->default(false);
            $table->unsignedSmallInteger('aviso_cobranca_dias_antes')->default(3);
            $table->string('template_cobranca')->nullable();     // 4 variáveis: nome, descrição, valor, vencimento
            $table->boolean('aviso_os')->default(false);
            $table->string('template_os_concluida')->nullable(); // 3 variáveis: nome, número da OS, equipamento
        });

        Schema::table('clients', function (Blueprint $table) {
            // O WhatsApp exige que o cliente tenha aceitado receber mensagens da empresa.
            $table->boolean('aceita_avisos_whatsapp')->default(false);
        });

        Schema::table('whatsapp_mensagens', function (Blueprint $table) {
            $table->string('evento')->nullable();   // aviso automático (cobranca_vencimento, cobranca_atrasada, os_concluida)
            $table->index(['tenant_id', 'evento', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensagens', fn (Blueprint $t) => $t->dropColumn('evento'));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('aceita_avisos_whatsapp'));
        Schema::table('tenant_whatsapp_settings', fn (Blueprint $t) => $t->dropColumn(['aviso_cobranca', 'aviso_cobranca_dias_antes', 'template_cobranca', 'aviso_os', 'template_os_concluida']));
    }
};
