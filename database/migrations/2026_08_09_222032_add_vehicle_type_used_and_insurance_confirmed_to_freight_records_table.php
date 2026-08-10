<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_records', function (Blueprint $table) {
            $table->string('vehicle_type_used')->nullable();
            $table->boolean('insurance_confirmed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('freight_records', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type_used', 'insurance_confirmed']);
        });
    }
};
