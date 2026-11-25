<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Degrau de plano contratado e marca de quando o exemplo foi oferecido; (basico, standard, premium) e a troca para um degrau menor, agendada para o fim do ciclo pago. */
    public function up(): void
    {
        Schema::table('intelligence_trials', function (Blueprint $table) {
            $table->string('plano_chave', 20)->nullable()->after('plano_preco');
            $table->string('plano_proximo_chave', 20)->nullable()->after('plano_chave');
            $table->date('plano_proximo_em')->nullable()->after('plano_proximo_chave');
            // Dados de exemplo gerados na 1ª abertura (uma vez só; depois o cliente pede de volta pelo botão).
            $table->timestamp('exemplo_oferecido_em')->nullable();
        });

        // Quem já contratou antes desta migration está no único plano que existia.
        DB::table('intelligence_trials')->whereNotNull('contracted_at')->update(['plano_chave' => 'basico']);
    }

    public function down(): void
    {
        Schema::table('intelligence_trials', function (Blueprint $table) {
            $table->dropColumn(['plano_chave', 'plano_proximo_chave', 'plano_proximo_em', 'exemplo_oferecido_em']);
        });
    }
};
