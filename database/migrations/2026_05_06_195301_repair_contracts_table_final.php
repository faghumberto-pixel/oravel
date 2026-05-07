<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Chaves Estrangeiras (UUID conforme padrão Oravel)
            if (!Schema::hasColumn('contracts', 'client_id')) {
                $table->foreignUuid('client_id')->nullable()->constrained()->restrictOnDelete();
            }
            if (!Schema::hasColumn('contracts', 'asset_id')) {
                $table->foreignUuid('asset_id')->nullable()->constrained()->restrictOnDelete();
            }

            // Atributos de Identificação e Controle
            if (!Schema::hasColumn('contracts', 'contract_number')) {
                $table->string('contract_number')->unique()->nullable();
            }
            if (!Schema::hasColumn('contracts', 'status')) {
                $table->string('status')->default('Draft');
            }

            // Datas, Valores e Termos
            if (!Schema::hasColumn('contracts', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'observations')) {
                $table->text('observations')->nullable();
            }
        });
    }

    public function down(): void { }
};