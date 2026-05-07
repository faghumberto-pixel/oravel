<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Chaves Estrangeiras (UUID)
            if (!Schema::hasColumn('contracts', 'client_id')) {
                $table->foreignUuid('client_id')->nullable()->constrained()->restrictOnDelete();
            }
            if (!Schema::hasColumn('contracts', 'asset_id')) {
                $table->foreignUuid('asset_id')->nullable()->constrained()->restrictOnDelete();
            }

            // Identificação e Status
            if (!Schema::hasColumn('contracts', 'contract_number')) {
                $table->string('contract_number')->unique()->nullable();
            }
            if (!Schema::hasColumn('contracts', 'status')) {
                $table->string('status')->default('Draft');
            }

            // Datas e Financeiro
            if (!Schema::hasColumn('contracts', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'price')) {
                $table->decimal('price', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('contracts', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }

            // Campos de Operação e Jurídico
            if (!Schema::hasColumn('contracts', 'observations')) {
                $table->text('observations')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'usage_purpose')) {
                $table->text('usage_purpose')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'required_nrs')) {
                $table->string('required_nrs')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        // Geralmente não removemos em migrations de sincronia para evitar perda de dados
    }
};