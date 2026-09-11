<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('landing_page_leads', function (Blueprint $table) {
            $table->timestamp('converted_at')->nullable()->after('contacted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_page_leads', function (Blueprint $table) {
            $table->dropColumn('converted_at');
        });
    }
};
