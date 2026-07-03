<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('maintenance_order_id')->constrained('maintenance_orders')->cascadeOnDelete();
            
            // Alterado para foreignId para compatibilizar com o ID da tabela users
            $table->uuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('observation')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_status_histories');
    }
};