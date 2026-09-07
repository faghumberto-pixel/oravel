<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * "Almoxarifado Volante" -- veiculo/tecnico de campo como deposito
     * secundario. type e' string (nao enum nativo do Postgres) pra seguir
     * o mesmo padrao ja usado em stock_movements.movement_type (string +
     * constantes no Model), evitando misturar 2 abordagens de enum na
     * mesma feature. Default 'central' preserva o significado dos
     * warehouses ja cadastrados sem exigir backfill manual.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type')->default('central')->after('code');
            $table->foreignUuid('user_id')->nullable()->after('manager_id')
                ->constrained('users')->nullOnDelete();
            $table->string('vehicle_plate')->nullable()->after('user_id');

            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['type', 'vehicle_plate']);
        });
    }
};
