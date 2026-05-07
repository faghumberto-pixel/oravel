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
    Schema::table('maintenance_order_checklists', function (Blueprint $table) {
        $table->uuid('checklist_group_id')->nullable();
        $table->string('checklist_type')->default('Preventiva'); // ex: Preventiva, Compra, Partida
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            //
        });
    }
};
