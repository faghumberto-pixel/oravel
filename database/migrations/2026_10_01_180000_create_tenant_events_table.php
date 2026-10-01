<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histórico permanente da relação cliente x Oravel (linha do tempo):
        // cadastro, contrato, assinatura, pagamentos, atrasos, anotações.
        Schema::create('tenant_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            // Evita duplicar o mesmo fato quando o Asaas reenvia o webhook.
            $table->string('dedupe_key')->nullable();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'dedupe_key']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_events');
    }
};
