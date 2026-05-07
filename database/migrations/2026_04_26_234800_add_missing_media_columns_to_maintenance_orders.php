<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_orders', 'photo_path')) {
                $table->string('photo_path')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'signature_path')) {
                $table->text('signature_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'signature_path']);
        });
    }
};