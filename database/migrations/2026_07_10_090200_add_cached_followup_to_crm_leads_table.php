<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->timestamp('last_contacted_at')->nullable()->after('longitude');
            $table->date('next_followup_date')->nullable()->after('last_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn(['last_contacted_at', 'next_followup_date']);
        });
    }
};
