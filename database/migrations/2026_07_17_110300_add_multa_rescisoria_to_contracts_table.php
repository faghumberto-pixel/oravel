<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract.multa_rescisoria e' um campo real do form (ContractResource,
 * secao "4. Responsabilidades e Rescisao") desde antes desta rodada, mas
 * nunca teve migration nem estava em $fillable -- ficava descartado
 * silenciosamente em todo save. Adicionado agora porque
 * AccountReceivable::calculateLateFee() passa a usar este valor como
 * sugestao padrao de multa (ver plano "Segmentacao Dinamica").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'multa_rescisoria')) {
                $table->decimal('multa_rescisoria', 5, 2)->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'multa_rescisoria')) {
                $table->dropColumn('multa_rescisoria');
            }
        });
    }
};
