<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compromisso da Programacao pode nascer de um Prospect (campo "Proximo contato"),
     * que ainda nao e' lead: sales_lead_id passa a ser opcional e ganha prospect_id.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE sales_lead_appointments ALTER COLUMN sales_lead_id DROP NOT NULL');

        Schema::table('sales_lead_appointments', function (Blueprint $table) {
            $table->foreignUuid('prospect_id')->nullable()->after('sales_lead_id')->constrained('prospects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_lead_appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prospect_id');
        });
    }
};
