<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_drivers', function (Blueprint $table) {
            // Vinculo com o cadastro de RH (Employee) para motorista proprio
            // (employment_type = proprio) -- terceiro (transportadora) nao
            // tem Employee, por isso fica nullable.
            $table->foreignUuid('employee_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fleet_drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
