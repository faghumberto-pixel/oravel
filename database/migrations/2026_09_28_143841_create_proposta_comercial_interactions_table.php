<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposta_comercial_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('proposta_comercial_id')->constrained('proposta_comerciais')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->string('channel');
            $table->dateTime('contact_date');
            $table->text('summary');
            $table->text('next_action')->nullable();
            $table->date('next_followup_date')->nullable();

            // Snapshot do status da proposta no momento desta interacao -- mesmo
            // padrao de CrmLeadInteraction::stage_at_time.
            $table->string('status_at_time');

            $table->timestamps();

            $table->index(['tenant_id', 'proposta_comercial_id', 'created_at']);
            $table->index(['tenant_id', 'next_followup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposta_comercial_interactions');
    }
};
