<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Conversas
        Schema::create("conversations", function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->string("name")->nullable(); 
            $table->boolean("is_group")->default(false);
            $table->foreignUuid("tenant_id")->constrained()->cascadeOnDelete(); 
            $table->timestamps();
        });

        // 2. Tabela de Mensagens
        Schema::create("messages", function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid("conversation_id")->constrained()->cascadeOnDelete();
            // VOLTANDO PARA foreignId porque sua tabela 'users' usa BIGINT
            $table->foreignId("user_id")->constrained()->cascadeOnDelete(); 
            $table->text("body")->nullable();
            $table->string("file_path")->nullable(); 
            $table->string("file_type")->nullable();
            $table->timestamp("read_at")->nullable();
            $table->timestamps();
        });

        // 3. Tabela Pivot
        Schema::create("conversation_user", function (Blueprint $table) {
            $table->id();
            $table->foreignUuid("conversation_id")->constrained()->cascadeOnDelete();
            // VOLTANDO PARA foreignId porque sua tabela 'users' usa BIGINT
            $table->foreignId("user_id")->constrained()->cascadeOnDelete(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("conversation_user");
        Schema::dropIfExists("messages");
        Schema::dropIfExists("conversations");
    }
};