<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * total_value nunca teve DEFAULT -- inofensivo enquanto o campo era
 * digitado a mao no form (sempre vinha preenchido no create()), mas
 * virou bug real quando a cotacao passou a ser itemizada
 * (2026_09_08_090400_create_material_request_quotation_items_table):
 * o campo no form agora e' so' leitura (disabled + dehydrated(false),
 * recalculado por MaterialRequestQuotationItemObserver DEPOIS que os
 * itens sao salvos), entao o create() da cotacao em si nunca manda
 * total_value -- sem DEFAULT, violava NOT NULL. Mesmo padrao ja' usado
 * em Quote::total_value (coluna com default 0 + $attributes no PHP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_quotations', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_request_quotations', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->default(null)->change();
        });
    }
};
