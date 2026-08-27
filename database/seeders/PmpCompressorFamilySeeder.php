<?php

namespace Database\Seeders;

use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateChecklistItem;
use App\Models\PmpTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Catalogo global (painel central, sem tenant_id) pro segmento
 * 'compressores' -- conteudo real fornecido pelo usuario 2026-08-27.
 * Diferente de Geradores (1 familia unica por subsistema), aqui o
 * documento ja separa por TIPO/tecnologia de compressor -- 4 familias,
 * mesmo padrao de Empilhadeiras. Checklist de inspecao fornecido e'
 * especifico de "Compressores Portateis a Diesel" (unico documento
 * completo recebido) -- so entra nessa familia, nao nas outras 3.
 *
 * Idempotente por nome: mesmo padrao dos outros seeders de PMP.
 *
 * Uso: php artisan db:seed --class=PmpCompressorFamilySeeder
 */
class PmpCompressorFamilySeeder extends Seeder
{
    private const SEGMENT = 'compressores';

    public function run(): void
    {
        $this->seedEstacionarioParafuso();
        $this->seedEstacionarioPistao();
        $portatilDiesel = $this->seedPortatilDiesel();
        $this->seedTratamentoAr();

        $this->seedInspectionChecklist($portatilDiesel);

        $this->command?->info('Catálogo PMP "Compressores": 4 famílias + checklist de inspeção (Portátil Diesel) semeados/atualizados.');
    }

    private function family(string $name, string $description): PmpEquipmentFamily
    {
        return PmpEquipmentFamily::firstOrCreate(
            ['segment' => self::SEGMENT, 'name' => $name],
            ['description' => $description],
        );
    }

    private function item(PmpEquipmentFamily $family, array $data): void
    {
        PmpTemplateItem::firstOrCreate(
            ['pmp_equipment_family_id' => $family->id, 'name' => $data['name']],
            [
                'periodicity_label' => $data['periodicity_label'],
                'interval_hours' => $data['interval_hours'] ?? null,
                'interval_days' => $data['interval_days'] ?? null,
                'is_critical' => $data['is_critical'] ?? false,
                'auto_create_order' => $data['auto_create_order'] ?? true,
                'notes' => $data['notes'] ?? null,
            ],
        );
    }

    private function checklistItem(PmpEquipmentFamily $family, string $section, string $itemName, int $sortOrder, ?string $instructions = null): void
    {
        PmpTemplateChecklistItem::firstOrCreate(
            ['pmp_equipment_family_id' => $family->id, 'item_name' => $itemName],
            ['section' => $section, 'sort_order' => $sortOrder, 'instructions' => $instructions],
        );
    }

    /**
     * Rotina diária comum a compressores elétricos -- pré-partida do
     * operador, sem a parte exclusiva de diesel (motor/combustível).
     */
    private function seedElectricDailyChecklist(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Nível de óleo do compressor (visor, máquina desligada e despressurizada)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Drenagem de condensado do reservatório/tanque e purgos dos filtros',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $this->item($family, [
            'name' => 'Indicador de restrição do filtro de ar no verde',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
    }

    /**
     * Rotina trimestral comum ao sistema do compressor (parafuso/pistão),
     * sem a parte exclusiva de motor diesel.
     */
    private function seedQuarterlyCompressorSystem(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Limpeza do elemento filtrante de ar de admissão (ou substituição em ambientes com poeira)',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Substituição do filtro de óleo lubrificante do elemento compressor',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de desgaste e tensionamento da correia ou elemento elástico de acoplamento',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Limpeza externa do radiador de ar/óleo (ar comprimido em sentido inverso ao fluxo)',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250,
        ]);
    }

    /**
     * Rotina semestral/anual comum (500-1000h) + rotina de segurança
     * NR-13 anual -- vasos de pressão, comuns a qualquer compressor com
     * reservatório.
     */
    private function seedSemiannualAndSafety(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Substituição do elemento separador de água/óleo',
            'periodicity_label' => '1000-2000h / Semestral-Anual', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Vital pra evitar contaminação da rede de ar e consumo excessivo de óleo.',
        ]);
        $this->item($family, [
            'name' => 'Troca integral do óleo do compressor (mineral, sintético ou semissintético)',
            'periodicity_label' => '1000-2000h / Semestral-Anual', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Respeitar o limite de horas do fabricante.',
        ]);
        $this->item($family, [
            'name' => 'Inspeção e substituição dos reparos de vedação da válvula mínima pressão e válvula termostática',
            'periodicity_label' => '1000-2000h / Semestral-Anual', 'interval_hours' => 1000,
        ]);
        $this->item($family, [
            'name' => 'Análise de vibração no elemento compressor e no motor (antecipar desgaste de rolamentos)',
            'periodicity_label' => '1000-2000h / Semestral-Anual', 'interval_hours' => 1000,
        ]);
        $this->item($family, [
            'name' => 'Teste de acionamento e aferição/calibração da válvula de segurança (alívio)',
            'periodicity_label' => 'Anual (NR-13)', 'interval_days' => 365, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Medição de espessura do reservatório por ultrassom (inspeção estrutural do vaso de pressão)',
            'periodicity_label' => 'Anual (NR-13)', 'interval_days' => 365, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Calibração do pressostato e sensores de temperatura (corte por alta temperatura/sobrepressão)',
            'periodicity_label' => 'Anual (NR-13)', 'interval_days' => 365, 'is_critical' => true,
        ]);
    }

    private function seedEstacionarioParafuso(): void
    {
        $family = $this->family(
            'Estacionário a Parafuso (Elétrico)',
            'Indústrias, oficinas e linhas fixas de ar -- elemento compressor, válvula de admissão regulável, filtro separador de água/óleo, trocador de calor elétrico.'
        );

        $this->seedElectricDailyChecklist($family);
        $this->seedQuarterlyCompressorSystem($family);
        $this->seedSemiannualAndSafety($family);
    }

    private function seedEstacionarioPistao(): void
    {
        $family = $this->family(
            'Estacionário a Pistão (Elétrico)',
            'Aplicações leves/médias, borracharias e suporte -- bloco cilíndrico, válvulas de palheta, correias de transmissão, pressostato, reservatório de ar.'
        );

        $this->seedElectricDailyChecklist($family);
        $this->seedQuarterlyCompressorSystem($family);
        $this->seedSemiannualAndSafety($family);
    }

    private function seedPortatilDiesel(): PmpEquipmentFamily
    {
        $family = $this->family(
            'Portátil a Parafuso (Motor Diesel)',
            'Locação, construção civil e perfuração -- motor diesel acoplado, chassi com reboque, válvulas reguladoras de pressão de carga/alívio, filtragem dupla (ar do motor + ar do compressor).'
        );

        // Diária -- comum + exclusivo diesel.
        $this->seedElectricDailyChecklist($family);
        $this->item($family, [
            'name' => 'Nível de óleo lubrificante do motor diesel e do líquido de arrefecimento',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Drenagem do copo decantador do filtro separador de água do diesel',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $this->item($family, [
            'name' => 'Inspeção visual de vazamentos de combustível e condição de pneus/engate do reboque',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);

        // Trimestral -- comum + exclusivo diesel.
        $this->seedQuarterlyCompressorSystem($family);
        $this->item($family, [
            'name' => 'Troca do óleo do motor diesel e do filtro de óleo',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Substituição do filtro de combustível primário e do filtro separador de água',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção da correia do alternador e do ventilador do radiador',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250,
        ]);

        $this->seedSemiannualAndSafety($family);

        // Cuidados específicos de compressores portáteis a diesel (item 3).
        $this->item($family, [
            'name' => 'Troca antecipada dos filtros de ar (motor + compressor) em locais com alta suspensão de particulado',
            'periodicity_label' => 'Conforme ambiente', 'interval_hours' => 125,
            'notes' => 'Dois circuitos de sucção de ar -- um pro motor, outro pro compressor. Antecipar em canteiro de obras/mineração.',
        ]);
        $this->item($family, [
            'name' => 'Teste de sincronismo do sistema de aceleração automática do diesel com a demanda de ar',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Partida semanal do equipamento parado no pátio (mais de 15 dias inativo)',
            'periodicity_label' => 'Semanal (se inativo)', 'interval_days' => 7,
            'notes' => 'Rodar até atingir temperatura operacional -- evita travamento da unidade compressora e cristalização do diesel.',
        ]);

        return $family;
    }

    private function seedTratamentoAr(): void
    {
        $family = $this->family(
            'Tratamento de Ar Comprimido',
            'Qualidade do ar na rede -- secadores de ar por refrigeração/dessecação, purgadores automáticos e filtros de linha (coalescentes).'
        );

        $this->item($family, [
            'name' => 'Drenagem de purgadores automáticos e verificação de funcionamento',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $this->item($family, [
            'name' => 'Substituição dos elementos filtrantes de linha (coalescentes)',
            'periodicity_label' => '1000-2000h / Semestral-Anual', 'interval_hours' => 1000, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção do secador de ar (refrigeração ou dessecação) -- ponto de orvalho e ciclo de regeneração',
            'periodicity_label' => '250-500h / Trimestral', 'interval_hours' => 250,
        ]);
    }

    /**
     * Checklist de Manutenção Preventiva — Compressores Portáteis a
     * Diesel (documento completo fornecido pelo usuário 2026-08-27),
     * 4 seções / 22 itens.
     */
    private function seedInspectionChecklist(PmpEquipmentFamily $family): void
    {
        $sort = 1;

        $section1 = '1. Motor a Diesel & Arrefecimento';
        $this->checklistItem($family, $section1, 'Nível de óleo lubrificante do motor a diesel (vareta)', $sort++);
        $this->checklistItem($family, $section1, 'Nível do líquido de arrefecimento no radiador / reservatório', $sort++);
        $this->checklistItem($family, $section1, 'Estado e tensão das correias do ventilador e do alternador', $sort++);
        $this->checklistItem($family, $section1, 'Drenagem de água/sedimentos no copo do filtro separador de diesel', $sort++);
        $this->checklistItem($family, $section1, 'Nível de combustível no tanque e estanqueidade das mangueiras', $sort++, 'Registrar nível em %.');
        $this->checklistItem($family, $section1, 'Elementos filtrantes de ar do motor (indicador de restrição/sujeira)', $sort++);
        $this->checklistItem($family, $section1, 'Ausência de vazamentos de óleo, combustível ou aditivo sob o chassi', $sort++);

        $section2 = '2. Unidade Compressora & Circuito de Ar/Óleo';
        $this->checklistItem($family, $section2, 'Nível de óleo da unidade compressora (visor com o compressor desligado)', $sort++);
        $this->checklistItem($family, $section2, 'Elemento filtrante de ar do compressor (indicador de restrição)', $sort++);
        $this->checklistItem($family, $section2, 'Drenagem de água e condensado do reservatório/separador', $sort++);
        $this->checklistItem($family, $section2, 'Radiador/trocador de calor de ar e óleo (limpeza das colmeias externas)', $sort++);
        $this->checklistItem($family, $section2, 'Visor de retorno de óleo da unidade compressora (pós-partida)', $sort++);

        $section3 = '3. Rodados, Chassi & Dispositivos de Segurança';
        $this->checklistItem($family, $section3, 'Calibragem, desgaste e aperto das porcas das rodas/pneus do reboque', $sort++);
        $this->checklistItem($family, $section3, 'Condição do engate mecânico, corrente de segurança e pé de apoio/macaco', $sort++);
        $this->checklistItem($family, $section3, 'Tensão e estado da bateria de partida (bornes limpos e fixados)', $sort++, 'Registrar voltagem em VDC.');
        $this->checklistItem($family, $section3, 'Válvula de segurança (alívio) de pressão do reservatório operante', $sort++);
        $this->checklistItem($family, $section3, 'Funcionamento do botão de parada de emergência e sensores de proteção', $sort++);

        $section4 = '4. Teste Operacional em Carga / Alívio';
        $this->checklistItem($family, $section4, 'Facilidade de partida do motor diesel e ausência de fumaça atípica', $sort++);
        $this->checklistItem($family, $section4, 'Pressão de trabalho com o registro de ar aberto (em carga)', $sort++, 'Registrar pressão em Bar/PSI.');
        $this->checklistItem($family, $section4, 'Atuação da válvula de admissão e desaceleração do motor ao fechar a saída (alívio)', $sort++);
        $this->checklistItem($family, $section4, 'Temperatura de descarga da unidade compressora e do motor estabilizadas', $sort++, 'Registrar temperatura óleo/ar em °C.');
        $this->checklistItem($family, $section4, 'Ausência de ruídos ou vibrações anormais na unidade compressora e acoplamento', $sort++);
    }
}
