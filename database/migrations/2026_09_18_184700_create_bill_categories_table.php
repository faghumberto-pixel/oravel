<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\BillCategory (Model + BillCategoryResource) existia desde
 * antes desta rodada mas nunca teve migration -- a tabela foi criada na
 * mao direto no banco em algum momento (comentario no proprio model dizia
 * isso), entao sobreviveu ate' um migrate:fresh apagar tudo (2026-09-18,
 * recuperacao de dados apos RefreshDatabase acidental na sessao) e a tela
 * "Categorias de Contas a Pagar" passar a dar 500 (relation
 * "bill_categories" does not exist). Mesmo gap ja documentado em
 * account_payables/account_receivables (bill_category_id sem FK real por
 * causa disso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_categories');
    }
};
