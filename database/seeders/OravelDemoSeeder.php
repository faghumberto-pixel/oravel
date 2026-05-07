<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Asset;
use App\Models\MaterialCategory;
use App\Models\Material;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenanceOrderMaterial;

class OravelDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pega o tenant_id do primeiro usuário administrador
        $user = User::first();
        if (!$user) {
            $this->command->error('Nenhum usuário encontrado. Crie uma conta no sistema primeiro.');
            return;
        }
        $tenantId = $user->tenant_id;

        // 2. Criar Categorias de Materiais
        $catEletrica = MaterialCategory::firstOrCreate(['name' => 'Elétrica', 'tenant_id' => $tenantId]);
        $catMecanica = MaterialCategory::firstOrCreate(['name' => 'Mecânica', 'tenant_id' => $tenantId]);
        $catEPI = MaterialCategory::firstOrCreate(['name' => 'EPI', 'tenant_id' => $tenantId]);

        // 3. Criar Materiais no Estoque
        $mat1 = Material::firstOrCreate(
            ['sku' => 'EL-001', 'tenant_id' => $tenantId],
            [
                'name' => 'Contator Tripolar 25A', 'category_id' => $catEletrica->id,
                'unit_cost' => 85.00, 'price' => 120.00, 'current_stock' => 15, 'min_stock' => 5, 'max_stock' => 50
            ]
        );
        
        $mat2 = Material::firstOrCreate(
            ['sku' => 'MC-001', 'tenant_id' => $tenantId],
            [
                'name' => 'Rolamento 6204 ZZ', 'category_id' => $catMecanica->id,
                'unit_cost' => 18.50, 'price' => 35.00, 'current_stock' => 40, 'min_stock' => 10, 'max_stock' => 100
            ]
        );

        $mat3 = Material::firstOrCreate(
            ['sku' => 'EP-001', 'tenant_id' => $tenantId],
            [
                'name' => 'Luva de Vaqueta', 'category_id' => $catEPI->id,
                'unit_cost' => 12.00, 'price' => 20.00, 'current_stock' => 100, 'min_stock' => 20, 'max_stock' => 200
            ]
        );

        // 4. Garantir que existe pelo menos um Ativo para abrir a OS
        $asset = Asset::firstOrCreate(
            ['tenant_id' => $tenantId, 'patrimonio' => 'TM-001'],
            [
                'name' => 'Torno Mecânico CNC', 
                'tag' => 'TAG-TM001', 
                'status' => 'Operacional'
            ]
        );

        // 5. Criar a Ordem de Serviço
        $prefix = 'OS-' . date('Ym') . '-';
        $os = MaintenanceOrder::firstOrCreate(
            ['os_number' => $prefix . '9999', 'tenant_id' => $tenantId],
            [
                'asset_id' => $asset->id,
                'technician_id' => $user->id,
                'description' => 'Manutenção Preventiva de 10.000 horas. Troca de rolamentos e verificação elétrica.',
                'technical_notes' => 'Rolamento do fuso principal apresentava folga excessiva. Substituição realizada.',
                'status' => 'Concluída'
            ]
        );

        // 6. Popular o Checklist da OS
        MaintenanceOrderChecklist::firstOrCreate([
            'maintenance_order_id' => $os->id,
            'tenant_id' => $tenantId,
            'category' => 'Preventiva',
            'section' => 'Mecânica',
            'item_name' => 'Inspecionar folga do fuso'
        ], ['is_completed' => true, 'is_template' => false]);

        MaintenanceOrderChecklist::firstOrCreate([
            'maintenance_order_id' => $os->id,
            'tenant_id' => $tenantId,
            'category' => 'Preventiva',
            'section' => 'Elétrica',
            'item_name' => 'Reapertar contatos no painel'
        ], ['is_completed' => true, 'is_template' => false]);

        // 7. Inserir os Materiais Usados na OS (CORRIGIDO: Inserindo o tenant_id)
        MaintenanceOrderMaterial::firstOrCreate([
            'maintenance_order_id' => $os->id,
            'name' => $mat2->name, // Nome do Rolamento
            'tenant_id' => $tenantId, // Aqui estava faltando!
        ], ['quantity' => 2]);

        MaintenanceOrderMaterial::firstOrCreate([
            'maintenance_order_id' => $os->id,
            'name' => $mat3->name, // Nome da Luva
            'tenant_id' => $tenantId, // Aqui estava faltando!
        ], ['quantity' => 1]);

        $this->command->info('Dados injetados com sucesso! Oravel está populado.');
    }
}