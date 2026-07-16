<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiplos contatos por prospect (nome, cargo, telefone, email) --
 * nao existia nenhum precedente disso em lugar nenhum do app (nem em
 * Client, que so tem um contact_name string solto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_lead_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_contacts');
    }
};
