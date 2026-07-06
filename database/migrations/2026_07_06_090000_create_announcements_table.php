<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aviso do sistema, criado pela operacao SaaS no painel Central --
     * NAO e dado por tenant (nao usa BelongsToTenant), e sim um recurso da
     * propria gestao da plataforma, direcionado a um tenant especifico ou a
     * todos (target_tenant_id nulo).
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message');
            $table->string('level')->default('info'); // info | warning | critical
            $table->foreignUuid('target_tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
