<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * filament-breezy ja tem um mecanismo pronto de upload de avatar
 * (FileUpload::make('avatar_url')->avatar(), so' precisa da coluna e do
 * flag ->myProfile(hasAvatars: true) ligado) -- sem reinventar com
 * MediaLibrary. FilamentManager::getUserAvatarUrl() ja cai pra essa
 * coluna automaticamente quando preenchida, senao usa o fallback de
 * iniciais (ui-avatars.com) que ja existe hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url')->nullable()->after('job_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_url')) {
                $table->dropColumn('avatar_url');
            }
        });
    }
};
