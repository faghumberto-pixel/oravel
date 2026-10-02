<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origem do anúncio no lead que chega do site (gclid + UTMs + página de entrada), para o time saber
 * qual campanha trouxe o lead e para, depois, enviar ao Google Ads quais leads viraram cliente.
 */
return new class extends Migration
{
    private const COLUMNS = ['gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column, 200)->nullable();
            }
            $table->string('landing_url', 255)->nullable();
            $table->index('gclid');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['gclid']);
            $table->dropColumn([...self::COLUMNS, 'landing_url']);
        });
    }
};
