<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // CRM e Financeiro (Pilar 1 e 4)
            $table->string('status')->default('trial'); // trial, active, past_due, canceled
            $table->decimal('mrr_value', 10, 2)->default(0.00); // Receita Recorrente Mensal
            $table->date('next_billing_date')->nullable();
            
            // Customer Success (Pilar 3)
            $table->boolean('onboarding_completed')->default(false);
            $table->integer('nps_score')->nullable();
            
            // Controle de Churn (Pilar 2)
            $table->timestamp('canceled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'mrr_value', 'next_billing_date', 
                'onboarding_completed', 'nps_score', 
                'canceled_at', 'cancellation_reason'
            ]);
        });
    }
};