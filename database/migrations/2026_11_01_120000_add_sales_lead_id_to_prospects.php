<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $t) {
            $t->foreignUuid('sales_lead_id')->nullable()->after('lote')->constrained('sales_leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sales_lead_id');
        });
    }
};
