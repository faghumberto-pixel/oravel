<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDatabaseUuid extends Command
{
    /**
     * O nome e a assinatura do comando.
     */
    protected $signature = 'db:fix-database';

    /**
     * Descrição do comando.
     */
    protected $description = 'Ajusta extensões, UUIDs e tipos de coluna do banco de dados';

    /**
     * Executa o comando.
     */
    public function handle()
    {
        $this->info('Iniciando ajustes no banco de dados...');
        
        try {
            // 1. Instala a extensão para UUIDs
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
            $this->info('Extensão uuid-ossp verificada.');
            
            // 2. Ajusta ID da tabela pivot
            DB::statement('ALTER TABLE maintenance_order_material ALTER COLUMN id SET DEFAULT uuid_generate_v4()');
            $this->info('ID da tabela pivot ajustado.');

            // 3. Ajusta o campo de assinatura para aceitar o texto longo (Base64)
            DB::statement('ALTER TABLE maintenance_orders ALTER COLUMN signature_path TYPE TEXT');
            $this->info('Campo de assinatura ajustado para TEXT.');
            
            $this->info('Banco de dados ajustado com sucesso!');
        } catch (\Exception $e) {
            $this->error('Erro ao ajustar o banco: ' . $e->getMessage());
        }
    }
}