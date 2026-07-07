<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('document')->nullable();
            $table->string('source')->nullable();

            $table->string('stage')->default('novo');
            $table->text('lost_reason')->nullable();

            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('estimated_value', 15, 2)->nullable();

            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('solicitacao_locacao_id')->nullable()->constrained('solicitacoes_locacao')->nullOnDelete();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
