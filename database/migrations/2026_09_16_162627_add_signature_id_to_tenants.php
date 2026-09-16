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
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('signature_id')->nullable();
            $table->foreign('signature_id')->references('id')->on('signatures')->nullOnDelete();
            $table->index('signature_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['signature_id']);
            $table->dropColumn('signature_id');
        });
    }
};
