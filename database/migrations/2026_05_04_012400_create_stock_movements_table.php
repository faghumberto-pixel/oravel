<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_units', function (Blueprint $table) {
            $cols = ['cep', 'address', 'city', 'state', 'latitude', 'longitude'];
            foreach ($cols as $col) {
                if (!Schema::hasColumn('internal_units', $col)) {
                    if ($col === 'state') $table->string($col, 2)->nullable();
                    elseif ($col === 'cep') $table->string($col, 10)->nullable();
                    elseif ($col === 'latitude' || $col === 'longitude') $table->decimal($col, 11, 8)->nullable();
                    else $table->string($col)->nullable();
                }
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