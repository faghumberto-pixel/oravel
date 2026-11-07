<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F6: politica de SLA especifica de um cliente e/ou de um contrato (nulo = vale para todos). A mais especifica vence. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
