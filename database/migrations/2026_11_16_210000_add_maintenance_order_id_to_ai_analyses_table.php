<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->foreignUuid('maintenance_order_id')->nullable()->after('crm_lead_id')->constrained('maintenance_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_order_id');
        });
    }
};
