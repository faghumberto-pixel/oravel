<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained();
            $table->string('type'); // avaria | comercial | estoque | logistica
            $table->foreignUuid('equipment_damage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('context');
            $table->json('response')->nullable();
            $table->string('status')->default('pendente'); // pendente | concluida | falhou
            $table->text('error')->nullable();
            $table->boolean('action_taken')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
