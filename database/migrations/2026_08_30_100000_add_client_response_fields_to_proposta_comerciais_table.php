<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->string('approval_token')->nullable()->unique()->after('rejection_reason');
            $table->timestamp('client_viewed_at')->nullable()->after('reviewed_at');
            $table->timestamp('client_responded_at')->nullable()->after('client_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->dropColumn(['approval_token', 'client_viewed_at', 'client_responded_at']);
        });
    }
};
