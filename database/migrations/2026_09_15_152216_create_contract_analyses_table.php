<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contract_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('contract_id');
            $table->longText('analysis');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->unique(['tenant_id', 'contract_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_analyses');
    }
};
