<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Itens Agregados separados em categorias: insumos (consumíveis, por
        // quantidade) x acessórios (físicos, unidade a unidade).
        Schema::table('aggregate_item_types', function (Blueprint $table) {
            $table->string('category')->default('acessorio')->after('tenant_id');
            $table->string('unit_of_measure', 20)->default('un')->after('name');
            $table->index(['tenant_id', 'category']);
        });

        // Mão de obra especializada: operadores, engenharia/planejamento, mobilização.
        Schema::create('specialized_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('service_type');
            $table->string('title');
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('status')->default('planejado');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'service_type']);
            $table->index(['tenant_id', 'status']);
        });

        // Segurança, conformidade e documentação: ART, laudos, calibração, seguros.
        Schema::create('compliance_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('number')->nullable();
            $table->string('issuer')->nullable();
            $table->foreignUuid('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->date('issue_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('coverage_value', 14, 2)->nullable();
            $table->string('responsible')->nullable();
            $table->string('attachment')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'document_type']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_documents');
        Schema::dropIfExists('specialized_services');
        Schema::table('aggregate_item_types', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'category']);
            $table->dropColumn(['category', 'unit_of_measure']);
        });
    }
};
