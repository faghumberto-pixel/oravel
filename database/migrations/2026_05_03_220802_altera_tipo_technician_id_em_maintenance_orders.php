<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite não suporta ALTER COLUMN TYPE, só roda no PostgreSQL
        if (DB::getDriverName() !== 'sqlite') {
            // Altera o tipo da coluna de UUID para BIGINT nativamente no PostgreSQL.
            // O "USING NULL" diz ao banco: se houver algum lixo em formato UUID salvo antes,
            // limpe apenas esse campo para não dar erro, mas MANTENHA a Ordem de Serviço intacta.
            DB::statement('ALTER TABLE maintenance_orders ALTER COLUMN technician_id TYPE bigint USING NULL');
        } elseif (Schema::hasColumn('maintenance_orders', 'technician_id')) {
            // No SQLite, a coluna já deve ser BIGINT (criada assim nas migrations anteriores)
            // ou será tratada automaticamente. Não faz nada.
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Caso precise reverter no futuro
            DB::statement('ALTER TABLE maintenance_orders ALTER COLUMN technician_id TYPE uuid USING NULL');
        }
    }
};