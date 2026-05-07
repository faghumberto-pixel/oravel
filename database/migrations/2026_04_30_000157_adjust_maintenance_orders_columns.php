<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Mudamos para 'text' para suportar JSON (Galeria) e Base64 longo (Assinatura)
            $table->text('photo_path')->nullable()->change();
            $table->text('signature_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->change();
            $table->string('signature_path', 255)->nullable()->change();
        });
    }
};