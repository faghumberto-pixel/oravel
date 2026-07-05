<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SolicitacaoLocacao::$fillable ja declarava cancellation_reason_id (usado
     * no form quando status_comercial = 'cancelado') mas a coluna nunca existiu
     * -- cancelar uma solicitacao com motivo quebrava a gravacao.
     */
    public function up(): void
    {
        Schema::table('solicitacoes_locacao', function (Blueprint $table) {
            $table->foreignUuid('cancellation_reason_id')->nullable()->after('status_comercial')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_locacao', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_reason_id');
        });
    }
};
