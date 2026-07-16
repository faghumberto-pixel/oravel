<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * InternalUnit vira a "filial" do estoque por localizacao (ver decisao
 * registrada no plano: reusar InternalUnit em vez de criar um 4o conceito
 * de "lugar" no sistema, ao lado de Location/Branch/InternalUnit ja
 * existentes). `code`/`is_active` ja existiam na tabela mas nunca em
 * $fillable/form -- aproveitando pra expor os dois junto dos novos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_units', function (Blueprint $table) {
            $table->foreignUuid('responsible_user_id')->nullable()->after('code')->constrained('users')->nullOnDelete();
            $table->string('type')->default('filial')->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('internal_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn('type');
        });
    }
};
