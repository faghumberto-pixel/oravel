<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\MaintenanceOrderChecklist;
use Illuminate\Support\Str;

class MaintenanceChecklistSeeder extends Seeder
{
    public function run(): void
    {
        // Busca o departamento que tem o código MANUT001 (ajuste se necessário)
        $department = Department::where('code', 'MANUT001')->first();

        if (!$department) {
            $this->command->error("Departamento MANUT001 não encontrado. Crie-o primeiro!");
            return;
        }

        $items = [
            '1. Dados de Identificação e Controle' => ['1.1 Identificação do Ativo', '1.2 Horímetro', '1.3 Localização', '1.4 Data e Inspetor'],
            '2. Sistema de Combustível' => ['2.1 Nível do Tanque', '2.2 Vazamentos', '2.3 Filtro Separador', '2.4 Qualidade do Combustível'],
            '3. Sistema de Lubrificação e Arrefecimento' => ['3.1 Nível do Óleo', '3.2 Condição do Óleo', '3.3 Líquido de Arrefecimento', '3.4 Radiador e Colmeia', '3.5 Mangueiras e Correias'],
            '4. Sistema Elétrico e de Partida' => ['4.1 Baterias', '4.2 Carregador de Bateria', '4.3 Painel de Controle (USCA)', '4.4 Cabos de Força'],
            '5. Inspeção Estrutural e de Segurança' => ['5.1 Filtro de Ar', '5.2 Sistema de Escape', '5.3 Vibrações e Ruídos', '5.4 Ambiente'],
            '6. Teste de Funcionamento' => ['6.1 Partida', '6.2 Tensão e Frequência', '6.3 Vazamentos sob Pressão', '6.4 Transferência']
        ];

        foreach ($items as $category => $subitems) {
            foreach ($subitems as $subitem) {
                MaintenanceOrderChecklist::create([
                    'department_id' => $department->id,
                    'tenant_id' => $department->tenant_id,
                    'code' => $department->code, // Mantém o código do dept como base
                    'category' => $category,
                    'item_name' => $subitem,
                    'is_template' => true, // Certifique-se de ter essa coluna se usar a lógica de templates
                ]);
            }
        }
    }
}