<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de qualificacao/fechamento do prospect -- source deixa de ser
 * texto livre pra virar um Select (com "outro" liberando texto livre
 * nele mesmo, sem coluna extra); lost_reason (ja existia, texto livre)
 * passa a ser o DETALHE de lost_reason_category (nova).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('segment')->nullable()->after('document');
            $table->string('company_size')->nullable()->after('segment');
            $table->decimal('estimated_revenue', 15, 2)->nullable()->after('company_size');
            $table->text('equipment_interest')->nullable()->after('estimated_revenue');
            $table->string('lost_reason_category')->nullable()->after('lost_reason');
            $table->text('won_notes')->nullable()->after('estimated_value');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn([
                'segment',
                'company_size',
                'estimated_revenue',
                'equipment_interest',
                'lost_reason_category',
                'won_notes',
            ]);
        });
    }
};
