<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('order_messages', 'chat_room_id')) {
                $table->foreignUuid('chat_room_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('chat_rooms')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_messages', function (Blueprint $table) {
            if (Schema::hasColumn('order_messages', 'chat_room_id')) {
                $table->dropForeign(['chat_room_id']);
                $table->dropColumn('chat_room_id');
            }
        });
    }
};