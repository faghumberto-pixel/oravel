<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract::$fillable/$casts ja declarava estas 9 colunas (prohibit_sublease,
     * maintenance_clause, initial_horimeter, initial_odometer, cep_obra,
     * latitude_obra, longitude_obra, legal_forum, insurance_details) mas elas
     * nunca existiram na tabela real -- qualquer Contract::create()/update() que
     * as tocasse quebrava com "column does not exist".
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('prohibit_sublease')->default(false)->after('required_nrs');
            $table->text('maintenance_clause')->nullable()->after('prohibit_sublease');
            $table->decimal('initial_horimeter', 12, 2)->nullable()->after('maintenance_clause');
            $table->decimal('initial_odometer', 12, 2)->nullable()->after('initial_horimeter');
            $table->string('cep_obra')->nullable()->after('initial_odometer');
            $table->decimal('latitude_obra', 10, 8)->nullable()->after('cep_obra');
            $table->decimal('longitude_obra', 11, 8)->nullable()->after('latitude_obra');
            $table->string('legal_forum')->nullable()->after('longitude_obra');
            $table->text('insurance_details')->nullable()->after('legal_forum');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'prohibit_sublease',
                'maintenance_clause',
                'initial_horimeter',
                'initial_odometer',
                'cep_obra',
                'latitude_obra',
                'longitude_obra',
                'legal_forum',
                'insurance_details',
            ]);
        });
    }
};
