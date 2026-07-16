<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger ganha de/para filial + motivo livre + referencia de documento --
 * antes so tinha o polimorfico "reference" (link interno). Tudo nullable:
 * as 3 chamadas existentes (compra/consumo/ajuste) continuam validas sem
 * passar esses campos, so a nova transferencia entre filiais os usa de
 * verdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->foreignUuid('from_location_id')->nullable()->after('material_id')->constrained('internal_units')->nullOnDelete();
            $table->foreignUuid('to_location_id')->nullable()->after('from_location_id')->constrained('internal_units')->nullOnDelete();
            $table->text('reason')->nullable()->after('type');
            $table->string('document_reference')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
            $table->dropColumn(['reason', 'document_reference']);
        });
    }
};
