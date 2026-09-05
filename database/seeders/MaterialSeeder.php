<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            ['sku' => 'PARA06', 'name' => 'Parafuso M6', 'category' => 'Fixadores'],
            ['sku' => 'PARA08', 'name' => 'Parafuso M8', 'category' => 'Fixadores'],
            ['sku' => 'PORC06', 'name' => 'Porca M6', 'category' => 'Fixadores'],
            ['sku' => 'PORC08', 'name' => 'Porca M8', 'category' => 'Fixadores'],
            ['sku' => 'ARRE06', 'name' => 'Arruela M6', 'category' => 'Fixadores'],
            ['sku' => 'ORRE10', 'name' => 'Corrente 10mm', 'category' => 'Correntes e Cabos'],
            ['sku' => 'ORRE12', 'name' => 'Corrente 12mm', 'category' => 'Correntes e Cabos'],
            ['sku' => 'CABO06', 'name' => 'Cabo de Aço 6mm', 'category' => 'Correntes e Cabos'],
            ['sku' => 'OHID00', 'name' => 'Óleo Hidráulico', 'category' => 'Fluidos'],
            ['sku' => 'OMOT15', 'name' => 'Óleo Motor 15W40', 'category' => 'Fluidos'],
            ['sku' => 'GRAX00', 'name' => 'Graxa NLGI 2', 'category' => 'Lubrificantes'],
            ['sku' => 'ROLE01', 'name' => 'Rolamento 6203', 'category' => 'Rolamentos'],
            ['sku' => 'ROLE02', 'name' => 'Rolamento 6205', 'category' => 'Rolamentos'],
            ['sku' => 'CORV00', 'name' => 'Correia V', 'category' => 'Transmissão'],
            ['sku' => 'CORSER', 'name' => 'Correia Serpentina', 'category' => 'Transmissão'],
            ['sku' => 'DISC00', 'name' => 'Disco de Freio', 'category' => 'Freios'],
            ['sku' => 'PAST00', 'name' => 'Pastilha de Freio', 'category' => 'Freios'],
            ['sku' => 'CLHI50', 'name' => 'Cilindro Hidráulico 50mm', 'category' => 'Hidráulica'],
            ['sku' => 'VALAL', 'name' => 'Válvula de Alívio', 'category' => 'Hidráulica'],
            ['sku' => 'FILTAR', 'name' => 'Filtro de Ar', 'category' => 'Filtros'],
        ];

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            foreach ($materials as $idx => $materialData) {
                $category = MaterialCategory::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $materialData['category'],
                    ],
                    [
                        'description' => "Categoria: {$materialData['category']}",
                    ]
                );

                Material::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'sku' => $materialData['sku'] . '-T' . substr($tenant->id, 0, 4),
                    ],
                    [
                        'name' => $materialData['name'],
                        'material_category_id' => $category->id,
                        'description' => "Material: {$materialData['name']}",
                        'unit' => 'un',
                        'minimum_stock' => rand(5, 20),
                    ]
                );
            }
            $this->command->info("✓ Materiais criados para {$tenant->name}");
        }

        $this->command->info('✅ Material seeder concluído!');
    }
}
