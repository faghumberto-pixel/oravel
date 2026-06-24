<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abc_matrices', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('nivel')->comment('A, B ou C');
            $table->string('descricao')->comment('Baixa, Media, Critica');
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abc_matrices');
    }
};
