<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chat_messages');

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('chat_room_id');
            $table->uuid('user_id');
            $table->text('message');
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('context_type')->nullable();
            $table->boolean('is_forwarded')->default(false);
            $table->uuid('tenant_id');
            $table->timestamps();

            $table->index('chat_room_id');
            $table->index('user_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
