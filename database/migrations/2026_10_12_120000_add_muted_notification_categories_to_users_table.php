<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Categorias de aviso que a pessoa escolheu NÃO receber (ver App\Support\NotificationCategories).
            $table->json('muted_notification_categories')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('muted_notification_categories');
        });
    }
};
