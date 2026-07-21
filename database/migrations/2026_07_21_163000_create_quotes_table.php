<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entidade de orçamento pro cliente final -- nao existia nenhuma antes
     * (ver auditoria de POPs, item 1 ja resolvido = infra de e-mail).
     * quotable_type/quotable_id (polimorfico) liga o orçamento numa
     * MaintenanceOrder (orçamento comum) ou EquipmentDamage (orçamento
     * indenizatorio) -- fica null quando e' um orçamento comercial solto,
     * sem ordem/avaria de origem.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->nullableUuidMorphs('quotable');
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('third_party_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('type')->default('interno');
            $table->string('status')->default('rascunho');

            // POP 2: laudo tecnico previo (avaliacao do tecnico antes de
            // montar o orçamento), so' preenchido em orçamento a terceiro.
            $table->text('technical_report')->nullable();

            $table->decimal('total_value', 15, 2)->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('client_viewed_at')->nullable();
            $table->timestamp('client_responded_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('financeiro_forwarded_at')->nullable();

            // Token do link publico assinado que o cliente abre pra
            // ver/aprovar/reprovar sem precisar logar (mesmo padrao de
            // portaria.verificar/{token} ja usado no sistema).
            $table->string('approval_token')->unique()->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
