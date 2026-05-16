<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('maintenance_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->text('message');
            $table->string('type')->default('user'); // user ou system (para automações)
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_messages'); }
};
