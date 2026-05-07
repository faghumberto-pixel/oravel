<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionamos a verificação para não tentar criar se já existir
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id');
                $table->foreignUuid('department_id');
                $table->string('name');
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};