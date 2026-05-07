<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('tag')->unique();
            $table->string('patrimonio')->nullable();
            $table->text('description')->nullable(); // Adicionado para evitar o erro de coluna
            $table->string('serial_number')->nullable();
            $table->string('status');
            $table->string('criticality')->default('medium'); // Definido um padrão para evitar erros em novos cadastros
            $table->foreignUuid('current_location_id')->nullable()->constrained('locations'); // Tornar nullable temporariamente para facilitar o seeding
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};