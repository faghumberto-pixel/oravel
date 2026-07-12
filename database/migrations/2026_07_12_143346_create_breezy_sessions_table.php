<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('breezy_sessions', function (Blueprint $table) {
            $table->id();
            // uuidMorphs, nao morphs() -- App\Models\User usa UUID como PK (HasUuids),
            // morphs() padrao cria authenticatable_id bigint e quebra a query (mesma
            // classe de bug documentada em activity_log.causer_id).
            $table->uuidMorphs('authenticatable');
            $table->string('panel_id')->nullable();
            $table->string('guard')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::dropIfExists('breezy_sessions');
    }
};
