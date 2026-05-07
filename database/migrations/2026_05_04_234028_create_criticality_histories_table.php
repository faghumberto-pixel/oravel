<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('criticality_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('old_level')->nullable();
            $table->string('new_level');
            $table->string('origin'); // Ex: Ordem de Serviço, Manual
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('criticality_histories'); }
};
