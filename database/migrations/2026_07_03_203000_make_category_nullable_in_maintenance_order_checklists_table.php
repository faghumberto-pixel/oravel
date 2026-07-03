<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
        });
    }
};
