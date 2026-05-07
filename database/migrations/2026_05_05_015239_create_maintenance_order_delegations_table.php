<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaintenanceOrderDelegationsTable extends Migration
{
    public function up()
    {
        // Intervenção mínima: Se a tabela já existir (causa do erro), apenas ignore e registre.
        if (!Schema::hasTable('maintenance_order_delegations')) {
            Schema::create('maintenance_order_delegations', function (Blueprint $table) {
                $table->id();
                // ... mantenha todas as suas colunas originais aqui ...
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_order_delegations');
    }
}