<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->string('channel');
            $table->dateTime('contact_date');
            $table->text('summary');
            $table->text('next_action')->nullable();
            $table->date('next_followup_date')->nullable();

            // Snapshot do estagio do lead no momento desta interacao -- reconstroi
            // o historico do funil sem precisar de uma tabela separada.
            $table->string('stage_at_time');

            $table->timestamps();

            $table->index(['tenant_id', 'crm_lead_id', 'created_at']);
            $table->index(['tenant_id', 'next_followup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_interactions');
    }
};
