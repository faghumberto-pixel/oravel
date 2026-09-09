<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliacao/scoring de um fornecedor (prazo de entrega, qualidade, preco)
 * -- normalmente uma linha por PurchaseOrder recebida (purchase_order_id
 * nullable pra permitir avaliacao avulsa). Sem Resource proprio,
 * gerenciada via RelationManager dentro do SupplierResource; score_medio
 * e' recalculado por App\Observers\SupplierEvaluationObserver, que tambem
 * atualiza o cache suppliers.rating_avg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('evaluated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score_prazo_entrega');
            $table->unsignedTinyInteger('score_qualidade');
            $table->unsignedTinyInteger('score_preco');
            $table->decimal('score_medio', 3, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('evaluated_at')->useCurrent();
            $table->timestamps();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('rating_avg');
        });

        Schema::dropIfExists('supplier_evaluations');
    }
};
