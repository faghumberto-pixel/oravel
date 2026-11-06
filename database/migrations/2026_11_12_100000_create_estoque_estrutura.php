<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque estruturado: grupos (Administracao, Equipamentos, Veiculos, Manutencao, Logistica...), enderecos do almoxarifado
 * (corredor > prateleira > nivel > posicao), onde cada item esta (materiais, pecas/oleo e itens agregados) e contagens fisicas
 * por endereco. Tudo aditivo: nenhuma tabela existente perde coluna ou dado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('internal_unit_id')->constrained('internal_units')->cascadeOnDelete();
            $table->string('aisle', 30); // corredor
            $table->string('shelf', 30); // prateleira
            $table->string('level', 30)->nullable(); // nivel
            $table->string('position', 30)->nullable(); // posicao/caixa
            $table->string('code', 120); // "C-A · P-03 · N-2" (montado automaticamente)
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'internal_unit_id', 'code'], 'stock_locations_code_unique');
            $table->index(['tenant_id', 'aisle']);
        });

        Schema::create('stock_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->string('item_type', 20); // material | part | aggregate
            $table->string('item_id', 40);
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['stock_location_id', 'item_type', 'item_id'], 'stock_placements_unique');
            $table->index(['tenant_id', 'item_type', 'item_id']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('internal_unit_id')->constrained('internal_units')->cascadeOnDelete();
            $table->foreignUuid('stock_group_id')->nullable()->constrained('stock_groups')->nullOnDelete();
            $table->string('aisle', 30)->nullable();
            $table->string('name');
            $table->string('status', 20)->default('em_contagem'); // em_contagem | finalizada
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignUuid('stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('location_code', 120)->nullable();
            $table->string('item_type', 20);
            $table->string('item_id', 40);
            $table->string('item_name');
            $table->string('item_sku', 80)->nullable();
            $table->string('group_name')->nullable();
            $table->decimal('expected_quantity', 14, 3)->default(0);
            $table->decimal('counted_quantity', 14, 3)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->index(['stock_count_id', 'stock_location_id']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignUuid('stock_group_id')->nullable()->constrained('stock_groups')->nullOnDelete();
        });
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignUuid('stock_group_id')->nullable()->constrained('stock_groups')->nullOnDelete();
        });
        Schema::table('aggregate_item_types', function (Blueprint $table) {
            $table->foreignUuid('stock_group_id')->nullable()->constrained('stock_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['materials', 'parts', 'aggregate_item_types'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropConstrainedForeignId('stock_group_id');
            });
        }
        foreach (['stock_count_items', 'stock_counts', 'stock_placements', 'stock_locations', 'stock_groups'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
