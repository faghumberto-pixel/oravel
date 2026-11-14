<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->foreignUuid('prospect_id')->nullable()->constrained()->cascadeOnDelete()->after('crm_lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropForeign(['prospect_id']);
            $table->dropColumn('prospect_id');
        });
    }
};