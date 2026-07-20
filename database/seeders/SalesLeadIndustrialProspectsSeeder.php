<?php

namespace Database\Seeders;

use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 5 prospects reais de locação/energia industrial, passados diretamente
 * pelo usuário (dores e soluções específicas por empresa, levantadas na
 * prospecção ativa). Geracamp e Geradores Campinas já existiam (criados
 * antes) -- aqui só recebem a dor/solução refinada, sem duplicar nem mexer
 * em segmento/responsável/estágio já definidos pra eles. CampGeradores,
 * Superinfra e CF Locações são novos.
 */
class SalesLeadIndustrialProspectsSeeder extends Seeder
{
    public function run(): void
    {
        $assignedUserId = User::where('email', 'humberto@oravel.com.br')->value('id');

        $prospects = [
            [
                'company_name' => 'CampGeradores',
                'website' => null,
                'city' => 'Campinas',
                'segment' => 'industrial_hospitalar',
                'critical_pain' => implode("\n", [
                    '• Dificuldade para acompanhar o histórico das máquinas no pátio.',
                    '• Retrabalho em preventivas/corretivas.',
                    '• Lentidão no fluxo da oficina.',
                ]),
                'oravel_solution' => implode("\n", [
                    '• Histórico Digital do Ativo: Registro completo por máquina.',
                    '• Quadro Kanban: Gestão visual do status da manutenção.',
                    '• Alerta de Horímetro: Prevenção de falhas recorrentes.',
                ]),
            ],
            [
                'company_name' => 'Superinfra',
                'website' => 'superinfra.com.br',
                'city' => null,
                'segment' => 'industrial_hospitalar',
                'critical_pain' => implode("\n", [
                    '• Falta de rastreabilidade formal no checklist de pátio.',
                    '• Demora no envio de laudos para aprovação.',
                    '• Mecânicos que evitam preencher relatórios extensos.',
                ]),
                'oravel_solution' => implode("\n", [
                    '• Áudio em Texto na O.S.: Mecânico narra e o sistema digita.',
                    '• Checklist com Fotos Obrigatórias: Comprovação técnica visual.',
                    '• Assinatura Digital: Liberação imediata sem papel.',
                ]),
            ],
            [
                'company_name' => 'Geradores Campinas',
                'website' => 'geradorescampinas.com.br',
                'city' => null,
                // valor real ja atribuido em PROD -- so' usado se este
                // seeder criar do zero (ex: banco resetado); no update
                // (caminho normal em PROD, lead ja existe) fica intocado.
                'segment' => 'construcao_civil',
                'critical_pain' => implode("\n", [
                    '• Ausência de ERP estruturado no pátio.',
                    '• Gargalos no giro e liberação rápida de geradores.',
                    '• Disputas sobre avarias pré-existentes na devolução.',
                ]),
                'oravel_solution' => implode("\n", [
                    '• Checklist Mobile via Celular: Agilidade na entrada/saída.',
                    '• Registro Fotográfico de Avarias: Blindagem jurídica e cobrança justa.',
                    '• Cronograma & Agenda: Visibilidade de pronta entrega.',
                ]),
            ],
            [
                'company_name' => 'CF Locações',
                'website' => 'cflocacoes.com.br',
                'city' => null,
                'segment' => 'construcao_civil',
                'critical_pain' => implode("\n", [
                    '• Mistura de diferentes tipos de ativos sem padronização.',
                    '• Perda de peças e acessórios no retorno de campo.',
                    '• Retrabalho na manutenção por diagnóstico impreciso.',
                ]),
                'oravel_solution' => implode("\n", [
                    '• Padronização de Checklists: Roteiros digitais personalizados.',
                    '• Rastreabilidade Blindada: Histórico técnico para eliminar o retrabalho.',
                    '• Painel do Gestor: Controle total da frota em tempo real.',
                ]),
            ],
            [
                'company_name' => 'Geracamp',
                'website' => 'geracamp.com.br',
                'city' => null,
                // idem Geradores Campinas acima: valor real ja em PROD, so'
                // usado se criar do zero.
                'segment' => 'industrial_hospitalar',
                'critical_pain' => implode("\n", [
                    '• Papelada física na oficina gerando extravio de informações.',
                    '• Falta de visão executiva do tempo de máquina parada.',
                    '• Comunicação ruidosa entre mecânicos e gestão.',
                ]),
                'oravel_solution' => implode("\n", [
                    '• Eliminação do Papel (Prancheta Zero): Operação 100% no smartphone.',
                    '• Quadro Kanban da Oficina: Visão clara de gargalos em tempo real.',
                    '• Relatórios Automáticos: Laudos gerados sem digitação manual.',
                ]),
            ],
        ];

        foreach ($prospects as $data) {
            $existing = SalesLead::where('company_name', $data['company_name'])->first();

            if ($existing) {
                $existing->update([
                    'critical_pain' => $data['critical_pain'],
                    'oravel_solution' => $data['oravel_solution'],
                ]);

                continue;
            }

            SalesLead::create([
                'company_name' => $data['company_name'],
                'website' => $data['website'],
                'city' => $data['city'],
                'segment' => $data['segment'],
                'source' => SalesLead::SOURCE_PROSPECCAO_ATIVA,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'critical_pain' => $data['critical_pain'],
                'oravel_solution' => $data['oravel_solution'],
                'assigned_user_id' => $assignedUserId,
            ]);
        }
    }
}
