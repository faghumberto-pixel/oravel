<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundled: brand_name/supplier_type ja existiam no form do MaterialResource
 * (Select com createOptionUsing) mas nunca tiveram coluna real -- salvavam
 * silenciosamente no vazio. Adicionadas aqui junto pois o form inteiro de
 * Materiais esta sendo revisado nesta mesma rodada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('part_number')->nullable()->after('sku');
            $table->string('barcode')->nullable()->after('part_number');
            $table->string('brand_name')->nullable()->after('name');
            $table->string('supplier_type')->nullable()->after('supplier_id');
            $table->string('unit_of_measure')->default('un')->after('max_stock');
            $table->string('warehouse_location')->nullable()->after('unit_of_measure');
            $table->decimal('last_purchase_price', 15, 2)->nullable()->after('unit_cost');
            $table->boolean('requires_serial_number')->default(false)->after('warehouse_location');
            $table->boolean('is_remanufactured')->default(false)->after('requires_serial_number');
            $table->unsignedInteger('warranty_days')->nullable()->after('is_remanufactured');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn([
                'part_number', 'barcode', 'brand_name', 'supplier_type',
                'unit_of_measure', 'warehouse_location', 'last_purchase_price',
                'requires_serial_number', 'is_remanufactured', 'warranty_days',
            ]);
        });
    }
};
