<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\CostCenter (Model + CostCenterResource) tinha o mesmo gap de
 * App\Models\BillCategory (ver 2026_09_18_184700_create_bill_categories_table.php
 * pro historico completo): existia so' no model, nunca teve migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
