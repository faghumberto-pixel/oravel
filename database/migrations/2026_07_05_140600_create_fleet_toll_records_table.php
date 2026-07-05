<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_toll_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fleet_vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freight_record_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('valor', 10, 2);
            $table->date('data');
            $table->string('praca_pedagio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_toll_records');
    }
};
