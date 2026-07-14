<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * material_requests.status ja' era string livre (sem CHECK constraint no
 * banco, so' um comentario no codigo listando os valores esperados) --
 * nao precisa de migration pra "ampliar enum", so' adicionar os 2
 * estagios que faltavam no INICIO do fluxo (rascunho/aguardando_aprovacao,
 * ver MaterialRequest::statusOptions()) e os campos de quem aprovou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignUuid('approved_by_user_id')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
        });
    }
};
