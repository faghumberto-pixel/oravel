<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->string('distribuicao')->default('fila');   // fila = quem atende assume | rodizio = distribui entre os atendentes
            $table->json('atendentes')->nullable();             // ids dos usuários que atendem o número da empresa
            $table->uuid('ultimo_atendente_id')->nullable();    // ponteiro do rodízio
        });

        Schema::table('whatsapp_conversas', function (Blueprint $table) {
            $table->timestamp('atribuida_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversas', fn (Blueprint $t) => $t->dropColumn('atribuida_em'));
        Schema::table('tenant_whatsapp_settings', fn (Blueprint $t) => $t->dropColumn(['distribuicao', 'atendentes', 'ultimo_atendente_id']));
    }
};
