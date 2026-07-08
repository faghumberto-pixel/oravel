<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('read_at');
            }
            if (! Schema::hasColumn('chat_messages', 'transcript')) {
                $table->text('transcript')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('chat_messages', 'transcript')) {
                $table->dropColumn('transcript');
            }
        });
    }
};
