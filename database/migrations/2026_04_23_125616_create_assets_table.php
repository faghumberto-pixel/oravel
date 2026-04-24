<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Alterado para uuid
            $table->uuid('tenant_id'); 
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('patrimonio')->unique();
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};