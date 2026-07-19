<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_lead_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->string('channel');
            $table->dateTime('contact_date');
            $table->text('summary');

            // Snapshot do estagio no momento da interacao -- reconstroi o
            // historico do funil sem tabela separada, mesmo padrao de
            // crm_lead_interactions.stage_at_time.
            $table->string('stage_at_time');

            $table->timestamps();

            $table->index(['sales_lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_interactions');
    }
};
