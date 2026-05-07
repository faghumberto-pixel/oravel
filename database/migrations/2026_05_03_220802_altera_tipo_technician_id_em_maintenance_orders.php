<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Altera o tipo da coluna de UUID para BIGINT nativamente no PostgreSQL.
        // O "USING NULL" diz ao banco: se houver algum lixo em formato UUID salvo antes,
        // limpe apenas esse campo para não dar erro, mas MANTENHA a Ordem de Serviço intacta.
        DB::statement('ALTER TABLE maintenance_orders ALTER COLUMN technician_id TYPE bigint USING NULL');
    }

    public function down(): void
    {
        // Caso precise reverter no futuro
        DB::statement('ALTER TABLE maintenance_orders ALTER COLUMN technician_id TYPE uuid USING NULL');
    }
};