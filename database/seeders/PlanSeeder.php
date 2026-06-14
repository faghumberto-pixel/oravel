<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'BASIC',
                'price' => 950.00, // Preço Promocional Cliente Fundador (MSRP original: 1290.00)
                'billing_cycle' => 'monthly',
                'features' => json_encode([
                    'focus' => 'Pequenas e Médias Locadoras (Construção Civil / Ferramentas)',
                    'max_technicians' => 5, // Limite de usuários técnicos no pátio
                    'reports' => [
                        'disponibilidade_ativos', // Painel comercial de Pátio vs Obra
                        'historico_custodia',     // Rastreabilidade de posse (com quem está o ativo)
                        'ponto_pedido_estoque'    // Gráfico de nível crítico para reposição de materiais
                    ],
                    'has_pwa_advanced' => false,
                    'has_bi_dashboards' => false,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'STANDARD',
                'price' => 2800.00, // Preço Promocional Sócio Fundador (MSRP original: 3200.00)
                'billing_cycle' => 'monthly',
                'features' => json_encode([
                    'focus' => 'Médio e Grande Porte (Geradores / Compressores / Oficina Ativa)',
                    'max_technicians' => null, // Ilimitados
                    'reports' => [
                        'cronograma_preventivas',   // Calendário automatizado por horímetro ou data
                        'custo_por_os',             // Composição financeira (Mão de Obra + Peças Aplicadas)
                        'indicadores_mtbf_mttr',    // Métricas de confiabilidade e quebra de máquinas
                        'produtividade_equipe'      // Relatório de eficiência de campo dos técnicos
                    ],
                    'has_pwa_advanced' => true,     // Libera o aplicativo de vistorias
                    'has_bi_dashboards' => false,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'PREMIUM',
                'price' => 0.00, // Sob Consulta / Customizado para grandes frotas
                'billing_cycle' => 'monthly',
                'features' => json_encode([
                    'focus' => 'Grandes Operações e Frotas Críticas (Linha Amarela / Guindastes)',
                    'max_technicians' => null,
                    'reports' => [
                        'roi_do_ativo',             // Faturamento de Locação vs Custos de Oficina vs Depreciação
                        'depreciacao_contabil',     // Perda de valor patrimonial gerencial mês a mês
                        'laudo_mobilizacao_digital',// Relatório fotográfico com assinatura digital e GPS anti-avarias
                        'dashboard_ebitda_master'   // Painel executivo de lucratividade global para diretores
                    ],
                    'has_pwa_advanced' => true,
                    'has_bi_dashboards' => true,    // Gráficos e BI avançados ativos
                ]),
                'is_active' => true,
            ]
        ];

        foreach ($plans as $plan) {
            // Como usamos UUID, buscamos pelo 'name' para evitar criar duplicados ao rodar o seeder mais de uma vez
            $exists = DB::table('plans')->where('name', $plan['name'])->first();

            if ($exists) {
                DB::table('plans')->where('id', $exists->id)->update([
                    'price' => $plan['price'],
                    'features' => $plan['features'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('plans')->insert([
                    'id' => Str::uuid()->toString(), // Gera o UUID compatível com o Tenant
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'billing_cycle' => $plan['billing_cycle'],
                    'features' => $plan['features'],
                    'is_active' => $plan['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}