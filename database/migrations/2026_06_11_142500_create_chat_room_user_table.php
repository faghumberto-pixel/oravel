<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_room_user')) {
            Schema::create('chat_room_user', function (Blueprint $table) {
                $table->foreignUuid('chat_room_id')
                    ->constrained('chat_rooms')
                    ->cascadeOnDelete();

                $table->uuid('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->primary(['chat_room_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_user');
    }
};
