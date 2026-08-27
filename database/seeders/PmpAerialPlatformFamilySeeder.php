<?php

namespace Database\Seeders;

use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateChecklistItem;
use App\Models\PmpTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Catalogo global (painel central, sem tenant_id) pro segmento
 * 'plataformas_elevatorias' -- conteudo real fornecido pelo usuario
 * 2026-08-27, focado em seguranca humana e conformidade com NR-18,
 * NR-12 e NR-35. Documento separa por categoria de plataforma -- 3
 * familias, mesmo padrao de Empilhadeiras/Compressores.
 *
 * Checklist de inspecao (2026-08-27, enviado em seguida) e' "Tesoura e
 * Lanca" -- vale pras 2 familias operacionais (Tesoura Eletrica,
 * Articulada/Lanca), NAO pra Sistema Eletronico de Seguranca (nao e' um
 * tipo de equipamento fisico, e' um agrupamento transversal de
 * componentes -- mesmo raciocinio de nao aplicar checklist la).
 *
 * Idempotente por nome: mesmo padrao dos outros seeders de PMP.
 *
 * Uso: php artisan db:seed --class=PmpAerialPlatformFamilySeeder
 */
class PmpAerialPlatformFamilySeeder extends Seeder
{
    private const SEGMENT = 'plataformas_elevatorias';

    public function run(): void
    {
        $tesoura = $this->seedTesouraEletrica();
        $lanca = $this->seedArticuladaLanca();
        $this->seedSistemaEletronicoSeguranca();

        $this->seedInspectionChecklist($tesoura);
        $this->seedInspectionChecklist($lanca);

        $this->command?->info('Catálogo PMP "Plataformas Elevatórias": 3 famílias + checklist (Tesoura/Lança) semeados/atualizados.');
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
     * Rotina diária comum a qualquer plataforma -- pré-operacional do
     * operador (item A do documento), aplicada às 3 famílias.
     */
    private function seedDailyChecklist(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Integridade do cesto, fechamento automático do portão e pontos de ancoragem do cinto paraquedista',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Chave seletora base/cesto e botões de parada de emergência em ambos os painéis',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Válvula manual de descida de emergência (bomba manual ou alavanca de alívio, sem carga)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Nível de óleo hidráulico e inspeção de vazamentos em cilindros e mangueiras visíveis',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
    }

    /**
     * Manutenção mensal (250h) — sistema hidráulico/mecânico comum,
     * aplicada às 3 famílias.
     */
    private function seedMonthlyHydraulicMechanical(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Inspeção das hastes dos cilindros (riscos, vazamentos pelos reparos/gaxetas) e buchas dos pinos de articulação',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de ressecamento/atrito/deformações nas mangueiras do catraca (esteira porta-cabos) e lança',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Limpeza externa e verificação de estanqueidade das solenoides do bloco hidráulico principal',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Torqueamento de parafusos de fixação do cesto, coroa de giro e rodas',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção das sanfonas de vedação (coifas) dos joysticks',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
            'notes' => 'Evita entrada de água no potenciômetro/hall-effect.',
        ]);
    }

    /**
     * Manutenção anual / ensaio de segurança (1000h, requisito NR-18) —
     * comum às 3 famílias.
     */
    private function seedAnnualSafetyTest(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Teste de carga / validação do sensor de peso (110-125% da capacidade do cesto)',
            'periodicity_label' => '1000h / Anual (NR-18)', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Calibra células de carga e valida o bloqueio automático de movimentos.',
        ]);
        $this->item($family, [
            'name' => 'Substituição total do óleo hidráulico e higienização interna do reservatório',
            'periodicity_label' => '1000h / Anual (NR-18)', 'interval_hours' => 1000,
        ]);
        $this->item($family, [
            'name' => 'Ensaio não destrutivo (líquido penetrante ou partículas magnéticas) nos cordões de solda de maior tensão',
            'periodicity_label' => '1000h / Anual (NR-18)', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Tesouras, base da lança, chassi e mesa girostática.',
        ]);
        $this->item($family, [
            'name' => 'Inspeção funcional do sensor de inclinação (Tilt Sensor)',
            'periodicity_label' => '1000h / Anual (NR-18)', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Simular elevação em rampa acima do limite -- deve bloquear subida e disparar alarme sonoro/visual.',
        ]);
    }

    private function seedTesouraEletrica(): PmpEquipmentFamily
    {
        $family = $this->family(
            'Tesoura Elétrica (Scissor)',
            'Baterias tracionárias/lítio, sistema hidráulico de elevação, motores elétricos de tração, mecanismo pantográfico -- nivelamento, proteção anti-buracos (Pothole Protection), freios de travamento negativo, desgaste de buchas das tesouras.'
        );

        $this->seedDailyChecklist($family);
        $this->item($family, [
            'name' => 'Abertura e travamento das saias laterais de proteção contra solavancos (Pothole Protection)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);

        $this->seedMonthlyHydraulicMechanical($family);
        $this->item($family, [
            'name' => 'Baterias chumbo-ácido: medição de tensão por vaso, água desmineralizada, limpeza dos bornes',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Baterias de lítio: leitura de relatórios do BMS e verificação do conector de carga',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);

        $this->item($family, [
            'name' => 'Substituição do filtro de retorno e do filtro de sucção/pressão do sistema hidráulico',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Checagem de folgas nos pinos mestre da direção, alinhamento das rodas e eficiência dos freios',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);

        $this->seedAnnualSafetyTest($family);

        return $family;
    }

    private function seedArticuladaLanca(): PmpEquipmentFamily
    {
        $family = $this->family(
            'Articulada / Lança (A Combustão / Híbrida)',
            'Motor diesel/bi-combustível, sistema hidráulico proporcional, lança telescópica, correntes/cabos de extensão, junta giratória (swivel joint) -- sensores de momento de carga, nivelamento do cesto, sensores de inclinação, válvulas seguradoras contra queda de lança.'
        );

        $this->seedDailyChecklist($family);
        $this->seedMonthlyHydraulicMechanical($family);
        $this->item($family, [
            'name' => 'Troca de óleo do motor e filtro de óleo (lanças diesel)',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Verificação do filtro de combustível e filtro de ar (lanças diesel)',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
        ]);

        $this->item($family, [
            'name' => 'Substituição do filtro de retorno e do filtro de sucção/pressão do sistema hidráulico',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de desgaste, equalização de tensão e lubrificação das correntes e cabos da lança',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500, 'is_critical' => true,
            'notes' => 'Graxa fluida adesiva para correntes/cabos.',
        ]);
        $this->item($family, [
            'name' => 'Nível de óleo da caixa de redução da coroa de giro e engraxamento dos dentes/rolamento',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500,
        ]);
        $this->item($family, [
            'name' => 'Checagem de folgas nos pinos mestre da direção, alinhamento das rodas e eficiência dos freios multidiscos',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);

        $this->seedAnnualSafetyTest($family);

        return $family;
    }

    private function seedSistemaEletronicoSeguranca(): void
    {
        $family = $this->family(
            'Sistema Eletrônico de Segurança',
            'Módulo de controle principal, joysticks, sensores de peso no cesto (Load Sense), chave seletora solo/cesto -- calibração de peso, chave de descida de emergência, botão de travamento (deadman switch), alarme de inclinação.'
        );

        $this->item($family, [
            'name' => 'Teste do botão de travamento (deadman switch) e resposta imediata ao soltar',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Teste do alarme de inclinação e resposta da chave de descida de emergência',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Calibração do sensor de peso do cesto (Load Sense) e chave seletora solo/cesto',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção do módulo de controle principal e conectores dos joysticks (proporcional)',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Teste funcional completo dos sensores de limite de curso e microinterruptores do mecanismo Pothole',
            'periodicity_label' => '500-600h / Semestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);

        $this->seedAnnualSafetyTest($family);
    }

    /**
     * Checklist de Manutenção Preventiva — Plataformas Elevatórias
     * (Tesoura e Lança), documento completo fornecido pelo usuário
     * 2026-08-27, 4 seções / 21 itens. Aplicado às 2 famílias
     * operacionais (Tesoura Elétrica, Articulada/Lança) -- não ao
     * Sistema Eletrônico de Segurança.
     */
    private function seedInspectionChecklist(PmpEquipmentFamily $family): void
    {
        $sort = 1;

        $section1 = '1. Estrutura, Cesto & Segurança do Operador';
        $this->checklistItem($family, $section1, 'Ponto de ancoragem do cinto de segurança (ausência de trincas/deformações)', $sort++);
        $this->checklistItem($family, $section1, 'Fechamento e travamento automático do portão/barra de entrada do cesto', $sort++);
        $this->checklistItem($family, $section1, 'Integridade dos guarda-corpos e assoalho antiderrapante do cesto', $sort++);
        $this->checklistItem($family, $section1, 'Hastes da tesoura / Seções da lança (ausência de empenamentos e trincas de solda)', $sort++);
        $this->checklistItem($family, $section1, 'Sistema anti-buracos (Pothole Protection) — abertura e recolhimento (Tesouras)', $sort++);
        $this->checklistItem($family, $section1, 'Pneus (sem cortes/deformações) e aperto das porcas das rodas', $sort++);

        $section2 = '2. Controles, Sensores & Sistema Elétrico/Alimentação';
        $this->checklistItem($family, $section2, 'Botões de parada de emergência (E-stop) operantes no cesto e na base', $sort++);
        $this->checklistItem($family, $section2, 'Chave seletora de controle (Painel Solo / Painel Cesto) funcionando', $sort++);
        $this->checklistItem($family, $section2, 'Joystick proporcional com retorno ao centro e coifa de borracha sem rasgos', $sort++);
        $this->checklistItem($family, $section2, 'Sensor de inclinação (Tilt Sensor) e alarmes sonoros/luminosos ativos', $sort++);
        $this->checklistItem($family, $section2, 'Baterias: nível de eletrólito, bornes limpos/apertados e cabos sem ressecamento', $sort++, 'Registrar voltagem em VDC.');
        $this->checklistItem($family, $section2, 'Nível de combustível, óleo do motor e arrefecimento (modelos a combustão)', $sort++);

        $section3 = '3. Sistema Hidráulico & Mecânica de Elevação';
        $this->checklistItem($family, $section3, 'Nível de óleo hidráulico no reservatório', $sort++);
        $this->checklistItem($family, $section3, 'Ausência de vazamentos em cilindros, blocos de válvulas e conexões', $sort++);
        $this->checklistItem($family, $section3, 'Estado das mangueiras no catraca (esteira guia) sem atrito ou deformação', $sort++);
        $this->checklistItem($family, $section3, 'Correntes, cabos de aço e roldanas da lança tensionados e lubrificados (Lança)', $sort++);
        $this->checklistItem($family, $section3, 'Engraxamento dos pinos de articulação, buchas e mesa do giratório', $sort++);

        $section4 = '4. Teste Operacional sem Carga';
        $this->checklistItem($family, $section4, 'Válvula / Sistema de descida manual de emergência testado e funcional', $sort++);
        $this->checklistItem($family, $section4, 'Elevação, descida, extensão e rotação com movimentos suaves (sem solavancos)', $sort++);
        $this->checklistItem($family, $section4, 'Atuação dos freios automáticos no momento de parada da tração', $sort++);
        $this->checklistItem($family, $section4, 'Redução automática da velocidade de tração quando o cesto está elevado', $sort++);
    }
}
