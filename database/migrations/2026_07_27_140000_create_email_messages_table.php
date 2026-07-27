<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caixa de e-mail (Enviados/Recebidos/Rascunhos). to_external e' a lista
     * crua de enderecos externos (cliente ou nao) -- destinatario interno
     * (outro usuario do mesmo tenant) fica na pivot email_message_recipients,
     * que permite lida/nao-lida por pessoa (um json aqui nao daria conta).
     * related_type/related_id liga o e-mail a um Client/CrmLead quando
     * disparado a partir da ficha de um deles; fica nulo em e-mail solto.
     */
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_user_id')->constrained('users')->cascadeOnDelete();

            $table->json('to_external')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('rascunho');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();

            $table->nullableUuidMorphs('related');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'from_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
