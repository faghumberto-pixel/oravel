<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('criticality_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code'); // A, B, M, D
            $table->string('name'); // Alta, Baixa...
            $table->string('color')->default('#ff0000');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('criticality_levels'); }
};
