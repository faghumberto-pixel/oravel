<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Garantir que a Innova Engenharia exista com o ID correto
        $innovaId = '019dbf98-582b-71b5-ba2f-8ec7f3ac98bd';
        $tenantInnova = Tenant::updateOrCreate(
            ['id' => $innovaId],
            ['name' => 'Innova Engenharia', 'slug' => 'innova-engenharia']
        );

        $this->command->info('Tenant configurado: ' . $tenantInnova->name);

        // 2. Limpeza prévia para garantir que não existam dados órfãos
        DB::table('maintenance_order_materials')->where('tenant_id', $innovaId)->delete();
        DB::table('maintenance_orders')->where('tenant_id', $innovaId)->delete();
        DB::table('assets')->where('tenant_id', $innovaId)->delete();
        DB::table('contracts')->where('tenant_id', $innovaId)->delete();
        DB::table('clients')->where('tenant_id', $innovaId)->delete();

        $now = Carbon::now();

        // 3. Clientes (20)
        $clientesIds = [];
        for ($i=1; $i<=20; $i++) {
            $cliId = (string)Str::uuid();
            DB::table('clients')->insert(['id' => $cliId, 'tenant_id' => $innovaId, 'name' => "Cliente $i", 'created_at' => $now, 'updated_at' => $now]);
            $clientesIds[] = $cliId;
        }

        // 4. Ativos e Contratos (20) - Inserção garantida com IDs válidos
        $ativosIds = [];
        $tiposAtivos = ['Gerador', 'Compressor', 'Plataforma', 'Empilhadeira', 'Guindaste'];
        
        foreach ($clientesIds as $index => $cliId) {
            $atId = (string)Str::uuid();
            $tipoIndex = $index % 5;

            DB::table('assets')->insert(['id' => $atId, 'tenant_id' => $innovaId, 'name' => "{$tiposAtivos[$tipoIndex]} ".($index+1), 'status' => 'operando', 'created_at' => $now, 'updated_at' => $now]);
            $ativosIds[] = $atId;
            
            DB::table('contracts')->insert([
                'id' => (string)Str::uuid(), 
                'tenant_id' => $innovaId, 
                'client_id' => $cliId, 
                'asset_id' => $atId, 
                'contract_number' => 'CTR-' . Str::random(8),
                'status' => 'active', 
                'start_date' => $now, 
                'created_at' => $now, 
                'updated_at' => $now
            ]);
        }

        // 5. Ordens de Serviço (20)
        foreach ($ativosIds as $aId) {
            DB::table('maintenance_orders')->insert(['id' => (string)Str::uuid(), 'tenant_id' => $innovaId, 'asset_id' => $aId, 'type' => 'preventive', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        }
        
        $this->command->info('Dados carregados com sucesso na Innova Engenharia!');
    }
}