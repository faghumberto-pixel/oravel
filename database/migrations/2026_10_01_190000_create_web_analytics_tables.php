<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Acessos ao SITE INSTITUCIONAL (oravel.com.br), coletados por um script
        // próprio (public/t.js) -- separado de site_visits, que mede o app.
        Schema::create('web_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('visitor_token', 64)->index();
            $table->string('session_token', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('referrer_host')->nullable()->index();
            $table->string('landing_path', 500)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0); // tempo ativo somado das páginas
            $table->boolean('is_returning')->default(false);
            $table->timestamp('started_at')->index();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('web_pageviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('web_visit_id')->constrained('web_visits')->cascadeOnDelete();
            $table->string('page_token', 64)->unique();
            $table->string('path', 500);
            $table->string('title')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedTinyInteger('max_scroll')->default(0); // % da página percorrida
            $table->timestamp('entered_at')->index();
            $table->timestamps();

            $table->index('path');
        });

        Schema::create('web_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('web_visit_id')->constrained('web_visits')->cascadeOnDelete();
            $table->string('type', 30); // click
            $table->string('label', 255);
            $table->string('path', 500)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['type', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_events');
        Schema::dropIfExists('web_pageviews');
        Schema::dropIfExists('web_visits');
    }
};
