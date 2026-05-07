<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // 1. Define se o serviço é Interno (na oficina) ou Externo (no cliente)
            // Usamos o padrão 'Interno' para não quebrar as OS que já existem no seu banco
            if (!Schema::hasColumn('maintenance_orders', 'service_type')) {
                $table->string('service_type')->default('Interno');
            }

            // 2. Cria a ligação com a tabela de Clientes
            // Crucial: Usamos foreignUuid porque a tabela 'clients' do Oravel usa UUID
            if (!Schema::hasColumn('maintenance_orders', 'client_id')) {
                $table->foreignUuid('client_id')
                    ->nullable()
                    ->constrained('clients')
                    ->onDelete('set null'); // Se o cliente for apagado, o histórico da OS é preservado
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Removemos a chave estrangeira primeiro para evitar erros de restrição
            if (Schema::hasColumn('maintenance_orders', 'client_id')) {
                $table->dropForeign(['client_id']);
            }

            // Removemos as colunas criadas
            $columnsToDrop = [];
            if (Schema::hasColumn('maintenance_orders', 'service_type')) {
                $columnsToDrop[] = 'service_type';
            }
            if (Schema::hasColumn('maintenance_orders', 'client_id')) {
                $columnsToDrop[] = 'client_id';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};