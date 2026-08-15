<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr_requirements_by_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_category_id')->constrained()->cascadeOnDelete();
            $table->string('norma');
            $table->timestamps();

            // Uma categoria de ativo pode exigir mais de uma norma (ex:
            // plataforma elevatoria exige NR-35 + NR-12), mas nao a mesma
            // norma duas vezes.
            $table->unique(['tenant_id', 'asset_category_id', 'norma']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nr_requirements_by_category');
    }
};
