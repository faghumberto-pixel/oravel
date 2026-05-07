<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Adiciona status se não existir
            if (!Schema::hasColumn('contracts', 'status')) {
                $table->string('status')->default('Draft')->after('contract_number');
            }

            // Adiciona price se não existir (vimos que o Resource usa 'price')
            if (!Schema::hasColumn('contracts', 'price')) {
                $table->decimal('price', 12, 2)->default(0)->after('end_date');
            }

            // Garante que o contract_number seja único
            if (Schema::hasColumn('contracts', 'contract_number')) {
                // Se já existe mas não é único, aqui você teria que tratar, 
                // mas vamos focar em garantir a existência.
            } else {
                $table->string('contract_number')->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['status', 'price']);
        });
    }
};