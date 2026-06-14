<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $materials = [
            [
                'name' => 'Correia de Transmissão p/ Betoneira 400L',
                'sku' => 'COR-BET-400',
                'current_stock' => 12,
                'unit_cost' => 45.00
            ],
            [
                'name' => 'Ponteiro Encaixe Sextavado p/ Rompedor 30kg',
                'sku' => 'PNT-ROM-30K',
                'current_stock' => 2,
                'unit_cost' => 189.00
            ],
            [
                'name' => 'Sapata de Borracha p/ Compactador de Solo',
                'sku' => 'SAP-COMP-01',
                'current_stock' => 8,
                'unit_cost' => 220.00
            ],
        ];

        foreach ($materials as $material) {
            // Buscamos se o SKU já existe para evitar duplicados
            $exists = DB::table('materials')->where('sku', $material['sku'])->first();

            if ($exists) {
                DB::table('materials')->where('id', $exists->id)->update([
                    'name' => $material['name'],
                    'current_stock' => $material['current_stock'],
                    'unit_cost' => $material['unit_cost'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('materials')->insert([
                    'id' => Str::uuid()->toString(), // Mantendo o padrão de UUID do projeto
                    'sku' => $material['sku'],
                    'name' => $material['name'],
                    'current_stock' => $material['current_stock'],
                    'unit_cost' => $material['unit_cost'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}