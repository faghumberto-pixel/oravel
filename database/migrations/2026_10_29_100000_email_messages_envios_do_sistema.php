<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('origem')->default('usuario'); // usuario = escrito na Caixa de E-mail; sistema = disparado pelo sistema
            $table->string('tipo')->nullable();           // ex.: ClientPortalAccessGranted, GenericPdfMail
        });

        DB::statement('ALTER TABLE email_messages ALTER COLUMN from_user_id DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn(['origem', 'tipo']);
        });
    }
};
