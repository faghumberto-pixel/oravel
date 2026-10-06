<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            // Lead que chegou pelo formulario do site da Oravel: a mensagem do visitante e de onde veio
            // (pagina, anuncio, utm...). Fica no funil de vendas da Oravel (Central), nunca no CRM de tenant.
            $table->text('inbound_message')->nullable();
            $table->json('inbound_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn(['inbound_message', 'inbound_details']);
        });
    }
};
