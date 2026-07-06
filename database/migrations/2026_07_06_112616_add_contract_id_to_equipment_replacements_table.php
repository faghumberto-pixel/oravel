<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_replacements', function (Blueprint $table) {
            $table->foreignUuid('contract_id')->nullable()->after('replacement_asset_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_replacements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
        });
    }
};
