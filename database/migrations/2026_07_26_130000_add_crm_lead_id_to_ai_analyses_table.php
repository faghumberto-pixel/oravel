<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->foreignUuid('crm_lead_id')->nullable()->after('equipment_damage_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_lead_id');
        });
    }
};
