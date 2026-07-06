<?php

namespace Database\Seeders;

use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Popula o checklist basico padrao por Grupo de equipamento (o "ponto de
 * partida" copiado -- snapshot -- sempre que uma OS e gerada para um ativo
 * daquele grupo). Idempotente: roda por tenant, nao duplica se rodado de
 * novo (firstOrCreate por nome do grupo/item).
 *
 * Uso: php artisan db:seed --class=BasicChecklistTemplateSeeder
 */
class BasicChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Geradores de Energia' => [
                'objetivo' => 'Evitar falhas de partida e superaquecimento.',
                'itens' => [
                    ['item_name' => 'Nível de Óleo do Motor', 'instructions' => 'Dentro da faixa'],
                    ['item_name' => 'Nível do Fluido de Arrefecimento', 'instructions' => 'Radiador'],
                    ['item_name' => 'Combustível', 'instructions' => 'Nível no tanque'],
                    ['item_name' => 'Bateria', 'instructions' => 'Tensão, oxidação nos bornes'],
                    ['item_name' => 'Vazamentos', 'instructions' => 'Óleo, combustível ou água'],
                    ['item_name' => 'Filtros', 'instructions' => 'Verificar obstrução visível'],
                    ['item_name' => 'Painel de Controle', 'instructions' => 'Sem alarmes/mensagens'],
                    ['item_name' => 'Escapamento', 'instructions' => 'Fixação e ausência de corrosão'],
                ],
            ],
            'Compressores de Ar' => [
                'objetivo' => 'Integridade do vaso de pressão (NR-13) e vedação.',
                'itens' => [
                    ['item_name' => 'Válvula de Segurança', 'instructions' => 'Teste manual - alívio'],
                    ['item_name' => 'Dreno do Reservatório', 'instructions' => 'Realizada purga'],
                    ['item_name' => 'Manômetro de Pressão', 'instructions' => 'Calibrado/visível'],
                    ['item_name' => 'Correias', 'instructions' => 'Tensão e estado de conservação'],
                    ['item_name' => 'Vazamentos de Ar', 'instructions' => 'Mangueiras/conexões'],
                    ['item_name' => 'Nível de Óleo do Cabeçote', 'instructions' => null],
                    ['item_name' => 'Chave de Emergência', 'instructions' => 'Acionamento/reset'],
                ],
            ],
            'Caminhão Munck' => [
                'objetivo' => 'Estabilidade e movimentação de carga.',
                'itens' => [
                    ['item_name' => 'Patolas', 'instructions' => 'Cilindros, calços e nivelamento'],
                    ['item_name' => 'Cabo de Aço', 'instructions' => 'Fios rompidos, nós ou oxidação'],
                    ['item_name' => 'Gancho', 'instructions' => 'Trava de segurança íntegra'],
                    ['item_name' => 'Lança Telescópica', 'instructions' => 'Trincas, soldas, empenos'],
                    ['item_name' => 'Controles/Alavancas', 'instructions' => 'Funcionalidade'],
                    ['item_name' => 'Sinalização Sonora/Visual', 'instructions' => 'De ré/giro'],
                    ['item_name' => 'Tabela de Carga', 'instructions' => 'Legível e presente'],
                ],
            ],
            'Plataformas Elevatórias' => [
                'objetivo' => 'Operação em altura com segurança total.',
                'itens' => [
                    ['item_name' => 'Joystick de Controle', 'instructions' => 'Resposta imediata'],
                    ['item_name' => 'Dispositivo de Descida de Emergência', 'instructions' => null],
                    ['item_name' => 'Sensor de Inclinação', 'instructions' => 'Teste de alarme'],
                    ['item_name' => 'Pneus/Rodas', 'instructions' => 'Integridade/corte/desgaste'],
                    ['item_name' => 'Guarda-Corpo da Cesta', 'instructions' => 'Travamento'],
                    ['item_name' => 'Bateria/Carga', 'instructions' => 'Nível da carga'],
                    ['item_name' => 'Buzina/Alarme de Movimentação', 'instructions' => null],
                ],
            ],
            'Guindastes' => [
                'objetivo' => 'Operação crítica de grande porte.',
                'itens' => [
                    ['item_name' => 'LMI', 'instructions' => 'Indicador de Momento de Carga - ON'],
                    ['item_name' => 'Freios de Giro e Elevação', 'instructions' => 'Teste funcional'],
                    ['item_name' => 'Cabo e Moitão', 'instructions' => 'Estado do cabo/rolamento'],
                    ['item_name' => 'Contra-peso', 'instructions' => 'Fixação e travamento'],
                    ['item_name' => 'Nivelamento do Guindaste', 'instructions' => 'Bolha de nível'],
                    ['item_name' => 'Mangueiras Hidráulicas', 'instructions' => 'Vazamentos/bolhas'],
                ],
            ],
        ];

        Tenant::all()->each(function (Tenant $tenant) use ($groups) {
            foreach ($groups as $groupName => $def) {
                $group = ChecklistGroup::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $groupName],
                    ['description' => $def['objetivo']]
                );

                foreach ($def['itens'] as $index => $item) {
                    MaintenanceOrderChecklist::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'checklist_group_id' => $group->id,
                            'item_name' => $item['item_name'],
                            'is_template' => true,
                        ],
                        [
                            'category' => $groupName,
                            'instructions' => $item['instructions'],
                            'section' => $groupName,
                            'checklist_type' => 'Preventiva',
                            'is_completed' => false,
                        ]
                    );
                }
            }
        });
    }
}
