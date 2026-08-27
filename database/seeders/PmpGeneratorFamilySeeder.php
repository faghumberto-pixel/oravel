<?php

namespace Database\Seeders;

use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateChecklistItem;
use App\Models\PmpTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Catalogo global (painel central, sem tenant_id) pro segmento
 * 'geradores' -- conteudo real fornecido pelo usuario 2026-08-27, focado
 * em confiabilidade de partida imediata, qualidade de energia e vida
 * util do motor/alternador/quadro de comando. Segmento separado de
 * 'empilhadeiras' (PmpEquipmentFamilySeeder) -- tecnologia e regime de
 * uso completamente diferentes (motor estacionario a diesel vs. tracao
 * eletrica/GLP intermitente).
 *
 * Uma unica familia "Grupos Geradores": o documento organiza por
 * SUBSISTEMA do mesmo equipamento (motor, arrefecimento, combustivel,
 * eletrico de partida, alternador, comando), nao por tipo de gerador --
 * diferente de Empilhadeiras, que tinha tecnologias distintas (eletrico
 * leve/pesado/combustao) que justificavam familias separadas.
 *
 * Idempotente por nome: mesmo padrao de PmpEquipmentFamilySeeder.
 *
 * Uso: php artisan db:seed --class=PmpGeneratorFamilySeeder
 */
class PmpGeneratorFamilySeeder extends Seeder
{
    private const SEGMENT = 'geradores';

    public function run(): void
    {
        $family = $this->family();

        $this->seedDailyChecklist($family);
        $this->seedMonthly50h($family);
        $this->seedQuarterly250h($family);
        $this->seedAnnualPredictive($family);
        $this->seedFuelCare($family);
        $this->seedInspectionChecklist($family);

        $this->command?->info('Catálogo PMP "Geradores": 1 família + checklist de inspeção semeados/atualizados.');
    }

    private function family(): PmpEquipmentFamily
    {
        return PmpEquipmentFamily::firstOrCreate(
            ['segment' => self::SEGMENT, 'name' => 'Grupos Geradores'],
            ['description' => 'Motor a combustão, arrefecimento, combustível, sistema elétrico de partida, alternador/potência e comando/automação (QTA, USCA) -- foco em confiabilidade de partida imediata e qualidade de energia.'],
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
     * A. Inspeção Visual Diária / Pré-Partida (Operador local).
     */
    private function seedDailyChecklist(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Nível de óleo lubrificante e nível do líquido de arrefecimento',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de vazamentos (óleo, combustível ou aditivo) sob o grupo gerador',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'USCA em modo "AUTOMÁTICO" e sem alarmes ativos',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Resistência de pré-aquecimento do bloco operante',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
    }

    /**
     * B. Manutenção Mensal (ou a cada 50 horas de uso).
     */
    private function seedMonthly50h(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Teste de partida sem carga (10-15 min, circulação de óleo e carga da bateria)',
            'periodicity_label' => '50h / Mensal', 'interval_hours' => 50, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Medição da tensão da bateria em repouso e durante a partida (queda de tensão)',
            'periodicity_label' => '50h / Mensal', 'interval_hours' => 50, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Limpeza e reaperto dos bornes da bateria com protetor contra oxidação',
            'periodicity_label' => '50h / Mensal', 'interval_hours' => 50,
        ]);
        $this->item($family, [
            'name' => 'Drenagem do copo decantador do filtro separador de água do diesel',
            'periodicity_label' => '50h / Mensal', 'interval_hours' => 50,
        ]);
        $this->item($family, [
            'name' => 'Inspeção do indicador de restrição e limpeza do elemento do filtro de ar',
            'periodicity_label' => '50h / Mensal', 'interval_hours' => 50,
        ]);
    }

    /**
     * C. Manutenção Trimestral / Semestral (ou a cada 250 horas).
     */
    private function seedQuarterly250h(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Troca do óleo lubrificante do motor e do filtro de óleo',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Substituição do filtro de combustível principal e do filtro separador de água',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de braçadeiras, mangueiras de diesel e linhas de retorno',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Concentração do aditivo anticorrosivo e pH do líquido de arrefecimento',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de desgaste e ajuste de tensão da correia do ventilador/alternador',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Substituição do elemento filtrante principal de ar',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Reaperto elétrico do QTA, disjuntor principal e bornes do alternador',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Sopragem leve das saídas de ar do alternador (evitar acúmulo de poeira nos enrolamentos)',
            'periodicity_label' => '250h / Trimestral', 'interval_hours' => 250,
        ]);
    }

    /**
     * D. Manutenção Anual / Preditiva (ou a cada 500-1.000 horas).
     */
    private function seedAnnualPredictive(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Teste com banco de carga resistiva (50%, 75%, 100% da nominal, 2-4h)',
            'periodicity_label' => '500-1000h / Anual', 'interval_hours' => 500, 'is_critical' => true,
            'notes' => 'Decarboniza o motor e valida a resposta do AVR e do governador de rotação.',
        ]);
        $this->item($family, [
            'name' => 'Análise laboratorial de amostra do óleo lubrificante',
            'periodicity_label' => '500-1000h / Anual', 'interval_hours' => 500,
            'notes' => 'Contaminação por fuligem, água, diesel ou desgaste de metais.',
        ]);
        $this->item($family, [
            'name' => 'Varetamento/limpeza química interna do radiador e troca total do fluido de arrefecimento',
            'periodicity_label' => '500-1000h / Anual', 'interval_hours' => 500,
        ]);
        $this->item($family, [
            'name' => 'Medição de isolação (megômetro) dos enrolamentos do estator e rotor',
            'periodicity_label' => '500-1000h / Anual', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Teste simulado das paradas de emergência (alta temperatura, baixa pressão de óleo, sobrevelocidade)',
            'periodicity_label' => '500-1000h / Anual', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Substituição preventiva da bateria de partida',
            'periodicity_label' => '24 meses', 'interval_days' => 730, 'is_critical' => true,
            'notes' => 'Independente do estado aparente -- disponibilidade da bateria é KPI do plano.',
        ]);
    }

    /**
     * Cuidados específicos com o combustível (diesel) -- item 3 do
     * documento, tratado como itens de plano com periodicidade própria.
     */
    private function seedFuelCare(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Polimento/filtragem do diesel no tanque',
            'periodicity_label' => '6 meses', 'interval_days' => 180,
            'notes' => 'Geradores de emergência com poucas horas rodadas -- diesel parado degrada por proliferação bacteriana.',
        ]);
        $this->item($family, [
            'name' => 'Limpeza interna e drenagem total do fundo do tanque',
            'periodicity_label' => '24 meses', 'interval_days' => 730,
            'notes' => 'Remoção de água condensada e sedimentos.',
        ]);
    }

    /**
     * Checklist de Manutenção Preventiva — Grupos Geradores (documento
     * completo fornecido pelo usuário 2026-08-27), 4 seções / 24 itens.
     */
    private function seedInspectionChecklist(PmpEquipmentFamily $family): void
    {
        $sort = 1;

        $section1 = '1. Inspeção do Motor & Fluidos';
        $this->checklistItem($family, $section1, 'Nível de óleo lubrificante do motor (vareta/medidor)', $sort++);
        $this->checklistItem($family, $section1, 'Condição do óleo (presença de borra, cheiro de combustível ou viscosidade)', $sort++);
        $this->checklistItem($family, $section1, 'Nível do líquido de arrefecimento no radiador/tanque de expansão', $sort++);
        $this->checklistItem($family, $section1, 'Condição e tensão das correias (ventilador e alternador de carga)', $sort++);
        $this->checklistItem($family, $section1, 'Funcionamento da resistência de pré-aquecimento do bloco (motor aquecido)', $sort++, 'Registrar temperatura medida em °C.');
        $this->checklistItem($family, $section1, 'Estado das mangueiras de arrefecimento e braçadeiras (ressecamento/folgas)', $sort++);
        $this->checklistItem($family, $section1, 'Ausência de vazamentos (óleo, líquido de arrefecimento ou combustível)', $sort++);

        $section2 = '2. Sistema de Combustível (Diesel)';
        $this->checklistItem($family, $section2, 'Nível de combustível no tanque diário / tanque mestre', $sort++, 'Registrar nível em %.');
        $this->checklistItem($family, $section2, 'Drenagem de água e sedimentos no filtro separador (copo decantador)', $sort++);
        $this->checklistItem($family, $section2, 'Inspeção das mangueiras, conexões e bomba manual de escorva', $sort++);
        $this->checklistItem($family, $section2, 'Bacia de contenção do tanque (ausência de acúmulo de óleo ou água)', $sort++);

        $section3 = '3. Sistema Elétrico de Partida & Comando';
        $this->checklistItem($family, $section3, 'Tensão da bateria em repouso (DC)', $sort++, 'Registrar voltagem em VDC.');
        $this->checklistItem($family, $section3, 'Tensão mínima da bateria durante a partida (queda máxima admitida)', $sort++, 'Registrar voltagem de partida em VDC.');
        $this->checklistItem($family, $section3, 'Estado dos bornes da bateria (limpeza, reaperto e aplicação de protetor/vaselina)', $sort++);
        $this->checklistItem($family, $section3, 'Carregador flutuante de bateria operante no painel (LED/Medidor OK)', $sort++);
        $this->checklistItem($family, $section3, 'Estado geral da fiação de comando e potência (sem bornes frouxos ou aquecimento)', $sort++);

        $section4 = '4. Teste Operacional (Simulação / Partida Manual)';
        $this->checklistItem($family, $section4, 'Tempo de resposta para partida e estabilização', $sort++, 'Registrar tempo em segundos.');
        $this->checklistItem($family, $section4, 'Frequência de saída do alternador (60 Hz)', $sort++, 'Registrar frequência medida em Hz.');
        $this->checklistItem($family, $section4, 'Tensão de saída fase-fase e fase-neutro sem carga', $sort++, 'Registrar V L1-L2 e demais fases.');
        $this->checklistItem($family, $section4, 'Pressão do óleo lubrificante com o motor em funcionamento', $sort++, 'Registrar pressão em BAR/PSI.');
        $this->checklistItem($family, $section4, 'Temperatura do motor estabilizada durante o teste', $sort++, 'Registrar temperatura final em °C.');
        $this->checklistItem($family, $section4, 'Ruídos anormais ou fumaça excessiva no escapamento (preta/azul/branca)', $sort++);
        $this->checklistItem($family, $section4, 'Botão de parada de emergência do painel/externo testado e operacional', $sort++);
        $this->checklistItem($family, $section4, 'Retorno do gerador para modo "AUTOMÁTICO" na USCA ao final do teste', $sort++);
    }
}
