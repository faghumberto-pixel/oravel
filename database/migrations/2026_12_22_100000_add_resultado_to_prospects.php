<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $t) {
            $t->string('resultado', 40)->nullable()->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $t) {
            $t->dropColumn('resultado');
        });
    }
};
