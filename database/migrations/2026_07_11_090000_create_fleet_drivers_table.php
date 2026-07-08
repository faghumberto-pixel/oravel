<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_drivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf')->nullable();
            $table->string('phone')->nullable();
            $table->string('employment_type')->default('proprio');
            $table->foreignUuid('freight_carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cnh_number')->nullable();
            $table->string('cnh_category')->nullable();
            $table->date('cnh_expiry_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_drivers');
    }
};
