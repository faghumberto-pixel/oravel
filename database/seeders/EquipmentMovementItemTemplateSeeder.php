<?php

namespace Database\Seeders;

use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\EquipmentPatioArrival;
use Illuminate\Database\Seeder;

class EquipmentMovementItemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            EquipmentMovement::TYPE_MOBILIZACAO => [
                ['section' => 'Estado do Equipamento', 'label' => 'Inspeção visual externa', 'requires_photo' => true],
                ['section' => 'Estado do Equipamento', 'label' => 'Nível de óleo do motor', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Nível de combustível', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Estado dos pneus/rodízios', 'requires_photo' => false],
                ['section' => 'Documentação e Segurança', 'label' => 'Sinalização e amarração da carga', 'requires_photo' => true],
                ['section' => 'Documentação e Segurança', 'label' => 'Conferência de documentação de transporte', 'requires_photo' => false],
                ['section' => 'Documentação e Segurança', 'label' => 'Acessórios e cabos inclusos conferidos', 'requires_photo' => false],
                ['section' => 'Registro', 'label' => 'Horímetro de saída', 'requires_photo' => false],
                ['section' => 'Registro', 'label' => 'Foto do painel/horímetro', 'requires_photo' => true],
            ],
            EquipmentMovement::TYPE_DESMOBILIZACAO => [
                ['section' => 'Estado do Equipamento', 'label' => 'Inspeção visual externa no retorno', 'requires_photo' => true],
                ['section' => 'Estado do Equipamento', 'label' => 'Nível de óleo do motor', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Nível de combustível', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Estado dos pneus/rodízios', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Verificação de avarias/danos', 'requires_photo' => true],
                ['section' => 'Documentação', 'label' => 'Conferência de acessórios e cabos devolvidos', 'requires_photo' => false],
                ['section' => 'Documentação', 'label' => 'Conferência de documentação de devolução', 'requires_photo' => false],
                ['section' => 'Registro', 'label' => 'Horímetro de retorno', 'requires_photo' => false],
                ['section' => 'Registro', 'label' => 'Foto do painel/horímetro', 'requires_photo' => true],
            ],
            // Laudo de Recebimento -- checklist da chegada FISICA no patio,
            // etapa seguinte (e distinta) do checklist de desmobilizacao feito
            // no cliente. Confere se o que chegou bate com o que saiu de la.
            EquipmentPatioArrival::TEMPLATE_TYPE => [
                ['section' => 'Estado do Equipamento', 'label' => 'Inspeção visual externa na chegada', 'requires_photo' => true],
                ['section' => 'Estado do Equipamento', 'label' => 'Nível de óleo do motor', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Nível de combustível', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Estado dos pneus/rodízios', 'requires_photo' => false],
                ['section' => 'Estado do Equipamento', 'label' => 'Verificação de avarias/danos novos', 'requires_photo' => true],
                ['section' => 'Documentação', 'label' => 'Conferência de acessórios e cabos devolvidos', 'requires_photo' => false],
                ['section' => 'Documentação', 'label' => 'Conferência de horímetro/odômetro contra o registro de saída', 'requires_photo' => false],
                ['section' => 'Registro', 'label' => 'Foto geral do equipamento no pátio', 'requires_photo' => true],
            ],
        ];

        foreach ($items as $type => $templates) {
            foreach ($templates as $index => $template) {
                EquipmentMovementItemTemplate::firstOrCreate(
                    ['type' => $type, 'label' => $template['label']],
                    [
                        'section' => $template['section'],
                        'sort_order' => $index + 1,
                        'requires_photo' => $template['requires_photo'],
                    ]
                );
            }
        }
    }
}
