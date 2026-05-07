<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_units', function (Blueprint $table) {
            if (!Schema::hasColumn('internal_units', 'cep')) {
                $table->string('cep', 10)->nullable();
            }
            if (!Schema::hasColumn('internal_units', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('internal_units', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('internal_units', 'state')) {
                $table->string('state', 2)->nullable();
            }
            if (!Schema::hasColumn('internal_units', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('internal_units', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('internal_units', function (Blueprint $table) {
            $table->dropColumn(['cep', 'address', 'city', 'state', 'latitude', 'longitude']);
        });
    }
};