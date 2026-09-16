<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('email');
            $table->string('name');
            $table->timestamp('signed_at');
            $table->string('hash', 64)->unique();
            $table->ipAddress('ip_origin')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
            $table->index('email');
            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
