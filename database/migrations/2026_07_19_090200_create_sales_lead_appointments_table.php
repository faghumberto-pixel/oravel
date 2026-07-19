<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agenda de compromissos do CRM comercial (tela "Programacao") --
     * mesmo papel de AgendaTecnicoWidget/CrmAgendaWidget (FullCalendar),
     * so que aqui o status e' o proprio compromisso (pendente/aguardando/
     * em_andamento/concluido), nao so uma data de follow-up.
     */
    public function up(): void
    {
        Schema::create('sales_lead_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('type')->default('demonstracao'); // demonstracao | ligacao | reuniao | outro
            $table->dateTime('scheduled_at');
            $table->string('status')->default('pendente'); // pendente | aguardando | em_andamento | concluido
            $table->timestamp('completed_at')->nullable();

            // Ultima vez que um alerta foi disparado pra esse compromisso --
            // evita notificar de novo a cada poll de 8s enquanto ele
            // continua vencido/proximo.
            $table->timestamp('last_alerted_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('sales_lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_appointments');
    }
};
