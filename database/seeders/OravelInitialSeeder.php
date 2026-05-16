<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OravelInitialSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tenant e Usuários (Mantemos Eloquent aqui)
        $tenant = Tenant::firstOrCreate(['slug' => 'oravel'], ['name' => 'ORAVEL MATRIZ']);

        $users = [
            ['name' => 'Gestor Oravel', 'email' => 'gestor@oravel.com.br'],
            ['name' => 'Adm Central', 'email' => 'adm@oravel.com.br'],
            ['name' => 'Tecnico Campo', 'email' => 'tecnico@oravel.com.br'],
            ['name' => 'Planejador PMP', 'email' => 'pmp@oravel.com.br'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'], 
                    'password' => bcrypt('password'),
                ]
            );
            $tenant->users()->syncWithoutDetaching([$user->id]);
        }

        // --- A PARTIR DAQUI USAMOS DB PARA IGNORAR REGRAS DE MODEL/UUID ---

        // 2. Limpeza prévia (Ordem correta para evitar erros de Foreign Key)
        DB::table('maintenance_orders')->delete();
        DB::table('assets')->delete();
        DB::table('asset_categories')->delete();
        DB::table('materials')->delete();

        // 3. Categorias de Ativos
        $categories = [
            'Terraplenagem', 'Elevação de Carga', 'Acesso em Altura', 
            'Produção de Concreto', 'Geração de Energia', 'Apoio Logístico'
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            $uuid = (string) Str::uuid();
            DB::table('asset_categories')->insert([
                'id' => $uuid,
                'name' => $cat,
                'slug' => Str::slug($cat),
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $catIds[$cat] = $uuid;
        }

        // 4. Ativos (10 Ativos conforme solicitado)
        $equipments = [
            ['name' => 'Escavadeira Hidráulica CAT 320', 'cat' => 'Terraplenagem'],
            ['name' => 'Pá Carregadeira Volvo L120', 'cat' => 'Terraplenagem'],
            ['name' => 'Motoniveladora John Deere 670G', 'cat' => 'Terraplenagem'],
            ['name' => 'Guindaste Tadano ATF 400', 'cat' => 'Elevação de Carga'],
            ['name' => 'Plataforma Articulada JLG 450AJ', 'cat' => 'Acesso em Altura'],
            ['name' => 'Bomba de Concreto Schwing', 'cat' => 'Produção de Concreto'],
            ['name' => 'Gerador Cummins 500kVA', 'cat' => 'Geração de Energia'],
            ['name' => 'Torre de Iluminação HiLight', 'cat' => 'Geração de Energia'],
            ['name' => 'Compressor de Ar Atlas Copco', 'cat' => 'Apoio Logístico'],
            ['name' => 'Caminhão Caçamba Scania G440', 'cat' => 'Apoio Logístico'],
        ];

        $assetIds = [];
        foreach ($equipments as $equip) {
            $assetUuid = (string) Str::uuid();
            DB::table('assets')->insert([
                'id' => $assetUuid,
                'name' => $equip['name'],
                'tag' => strtoupper(Str::random(6)),
                'asset_category_id' => $catIds[$equip['cat']],
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $assetIds[] = $assetUuid;
        }

        // 5. Materiais (Ajustado para colunas existentes no banco)
        $materialsList = [
            'Óleo Lubrificante 15W40', 'Filtro de Combustível', 'Graxa de Lítio',
            'Líquido de Arrefecimento', 'Mangueira Hidráulica 1/2', 'Dente de Caçamba',
            'Pneu 20.5 R25', 'Bateria 150Ah', 'Kit Reparo Cilindro', 'Desengraxante'
        ];

        foreach ($materialsList as $mat) {
            $data = [
                'id' => (string) Str::uuid(),
                'sku' => 'MAT-' . strtoupper(Str::random(8)),
                'name' => $mat,
                'tenant_id' => $tenant->id,
                'stock_quantity' => rand(10, 100),
                'unit' => 'un',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Adiciona unit_cost apenas se a coluna existir (evita erro Undefined Column)
            if (Schema::hasColumn('materials', 'unit_cost')) {
                $data['unit_cost'] = rand(100, 500);
            }

            DB::table('materials')->insert($data);
        }

        // 6. Ordens de Serviço (2 Preventivas, 1 Corretiva)
        $osData = [
            ['asset' => $assetIds[0], 'type' => 'preventiva', 'desc' => 'Troca de óleo e filtros 250h', 'status' => 'open'],
            ['asset' => $assetIds[6], 'type' => 'preventiva', 'desc' => 'Revisão periódica sistema elétrico', 'status' => 'pending'],
            ['asset' => $assetIds[4], 'type' => 'corretiva', 'desc' => 'Vazamento em mangueira de alta pressão', 'status' => 'in_progress'],
        ];

        foreach ($osData as $os) {
            DB::table('maintenance_orders')->insert([
                'id' => (string) Str::uuid(),
                'asset_id' => $os['asset'],
                'tenant_id' => $tenant->id,
                'type' => $os['type'],
                'description' => $os['desc'],
                'status' => $os['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}