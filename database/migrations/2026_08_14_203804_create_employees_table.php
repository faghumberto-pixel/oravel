<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Colaborador nem sempre loga no painel (operador de campo tipico
            // nao precisa) -- FK opcional pra quem tambem tem User, mesmo
            // padrao de FleetDriver (sem user_id nenhum) estendido com o
            // vinculo pra quem loga (ex: Gerente de RH).
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('cpf', 11);
            $table->string('role_title')->nullable();
            $table->string('status')->default('ativo');
            $table->date('admission_date')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cpf']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
