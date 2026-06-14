<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client; 
use App\Models\AssetCategory; 
use App\Models\Asset;
use App\Models\SolicitacaoLocacao; 
use App\Models\MaintenanceOrder; 

class TestingDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Definição do Tenant Fixo
        $tenantId = '019e9ce6-9b89-7029-b352-fd4384a4ec0e';

        // 2. Usuário Master Admin
        $userAdmin = User::updateOrCreate(
            [
                'email' => 'humberto@oravel.com.br'
            ],
            [
                'name' => 'Humberto Vasconcelos',
                'password' => bcrypt('senha_segura_123'), 
                'tenant_id' => $tenantId,
                'role' => 'admin',
            ]
        );

        // 3. Usuário Vendedor
        $userVendedor = User::updateOrCreate(
            [
                'email' => 'comercial@oravel.com.br'
            ],
            [
                'name' => 'Marcos Comercial',
                'password' => bcrypt('senha_segura_123'),
                'tenant_id' => $tenantId,
                'role' => 'vendedor',
            ]
        );

        // 4. Cadastro de Clientes (Clients)
        $clienteRMC = Client::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'RMC Plataformas Elevatórias'
            ],
            [
                'phone' => '(19) 3224-0010',
            ]
        );

        Client::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'Geradores Campinas Ltda'
            ],
            [
                'phone' => '(19) 3232-1550',
            ]
        );

        // 5. Categorias de Equipamentos (Asset Categories)
        $catPlataforma = AssetCategory::updateOrCreate(
            [
                'tenant_id' => $tenantId, 
                'slug' => 'plataforma-articulada-16m'
            ],
            [
                'name' => 'Plataforma Articulada 16m'
            ]
        );

        // 6. Ativos da Frota (Assets)
        $plataformaAtivo = Asset::updateOrCreate(
            [
                'tenant_id' => $tenantId, 
                'serial_number' => 'JLG-16M-2026'
            ],
            [
                'asset_category_id' => $catPlataforma->id,
                'name' => 'Plataforma Elevatória JLG 450AJ',
                'status' => 'manutencao',
                'horimetro_atual' => 1240,
            ]
        );

    // 7. POVOANDO AS SOLICITAÇÕES DE LOCAÇÃO
        SolicitacaoLocacao::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'customer_id' => $clienteRMC->id, 
                'asset_id' => $plataformaAtivo->id, 
            ],
            [
                'category_id' => $catPlataforma->id,           // Obrigatório pelo erro anterior
                'data_saida_prevista' => now()->addDays(5),    // Obrigatório pelo erro atual
                'status_comercial' => 'contrato_fechado',
                'user_id' => $userVendedor->id,
            ]
        );

        // 8. Ordem de Serviço de Oficina (Maintenance Orders)
        MaintenanceOrder::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'asset_id' => $plataformaAtivo->id,
            ],
            [
                'description' => 'Revisão geral preventiva do sistema hidráulico do braço e check de joystick.',
            ]
        );
    }
}