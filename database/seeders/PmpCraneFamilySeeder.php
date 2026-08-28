<?php

namespace Database\Seeders;

use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateChecklistItem;
use App\Models\PmpTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Catalogo global (painel central, sem tenant_id) pro segmento
 * 'guindastes_munck' -- guindastes hidraulicos articulados/telescopicos
 * montados sobre caminhao (Munck), foco no cliente prospect Gêmeos
 * Guindastes (locação). Diferente do "guindaste" como plataforma de
 * elevacao de pessoas (Plataformas Elevatorias ja tem catalogo proprio)
 * -- aqui e' equipamento de movimentacao de carga.
 *
 * Itens baseados em pratica documentada de NR-11 (transporte/movimentacao
 * de cargas), NR-12 (seguranca em maquinas -- plano de manutencao
 * preventiva documentado por hora-maquina e' exigencia normativa, nao so
 * boa pratica) e NBR 8400 (calculo estrutural de guindastes -- criterio
 * de descarte de cabo de aco por fios rompidos por passo de torcao).
 * Pesquisa 2026-08-28, sem documento tecnico fornecido pelo usuario --
 * revisar/ajustar quando ele validar contra a pratica real da Gêmeos.
 *
 * Uma unica familia "Guindastes Articulados (Munck)": o equipamento e'
 * uma unidade so (chassi do caminhao + guindaste), mesmo padrao de
 * PmpGeneratorFamilySeeder (subsistemas do mesmo equipamento, nao
 * tecnologias distintas).
 *
 * Idempotente por nome: mesmo padrao dos outros seeders de PMP.
 *
 * Uso: php artisan db:seed --class=PmpCraneFamilySeeder
 */
class PmpCraneFamilySeeder extends Seeder
{
    private const SEGMENT = 'guindastes_munck';

    public function run(): void
    {
        $family = $this->family();

        $this->seedDailyChecklist($family);
        $this->seedWeekly($family);
        $this->seedMonthly($family);
        $this->seedSemiannual($family);
        $this->seedAnnual($family);
        $this->seedInspectionChecklist($family);

        $this->command?->info('Catálogo PMP "Guindastes/Munck": 1 família + checklist de inspeção semeados/atualizados.');
    }

    private function family(): PmpEquipmentFamily
    {
        return PmpEquipmentFamily::firstOrCreate(
            ['segment' => self::SEGMENT, 'name' => 'Guindastes Articulados (Munck)'],
            ['description' => 'Guindaste hidráulico articulado/telescópico montado sobre chassi de caminhão -- sistema hidráulico, lança, cabos de aço, gancho e chassi de transporte. Conformidade NR-11/NR-12/NBR 8400.'],
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
     * Inspeção pré-operacional diária -- exigência NR-12 antes de iniciar
     * qualquer operação de içamento.
     */
    private function seedDailyChecklist(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Inspeção visual do gancho (trincas, deformação, trava de segurança funcional)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de vazamentos no sistema hidráulico (cilindros, mangueiras, conexões)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Teste de funcionamento dos comandos e movimentos (giro, lança, extensão, guincho)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Verificação visual do cabo de aço (fios rompidos, amassamento, corrosão)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
            'notes' => 'Critério de descarte por número de fios rompidos por passo de torção -- NBR 8400.',
        ]);
        $this->item($family, [
            'name' => 'Verificação dos estabilizadores/sapatas (extensão total, travamento)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Nível de óleo hidráulico',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
    }

    /**
     * Rotina semanal -- itens que não fazem sentido revisar toda partida
     * mas exigem acompanhamento frequente.
     */
    private function seedWeekly(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Lubrificação dos pontos de articulação da lança e giro',
            'periodicity_label' => 'Semanal', 'interval_days' => 7,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de fixação de parafusos e porcas estruturais visíveis',
            'periodicity_label' => 'Semanal', 'interval_days' => 7,
        ]);
        $this->item($family, [
            'name' => 'Teste dos dispositivos de segurança (limitador de carga, fim de curso, alarme sonoro de içamento)',
            'periodicity_label' => 'Semanal', 'interval_days' => 7, 'is_critical' => true,
        ]);
    }

    /**
     * Rotina mensal.
     */
    private function seedMonthly(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Troca/verificação do filtro do sistema hidráulico',
            'periodicity_label' => 'Mensal', 'interval_days' => 30,
        ]);
        $this->item($family, [
            'name' => 'Inspeção do sistema elétrico (iluminação, sinalização, painel de comando)',
            'periodicity_label' => 'Mensal', 'interval_days' => 30,
        ]);
        $this->item($family, [
            'name' => 'Verificação de folgas nos mancais e buchas de articulação',
            'periodicity_label' => 'Mensal', 'interval_days' => 30,
        ]);
    }

    /**
     * Semestral -- inclui o ensaio não destrutivo de gancho/cabo citado
     * como item de fiscalização de campo na pesquisa.
     */
    private function seedSemiannual(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Ensaio não destrutivo (END) do gancho principal',
            'periodicity_label' => 'Semestral', 'interval_days' => 180, 'is_critical' => true,
            'notes' => 'Registro obrigatório no livro de manutenção -- exigido em fiscalização NR-11/NR-12.',
        ]);
        $this->item($family, [
            'name' => 'Troca do óleo hidráulico',
            'periodicity_label' => 'Semestral', 'interval_days' => 180,
        ]);
        $this->item($family, [
            'name' => 'Inspeção estrutural completa da lança (trincas, deformações, solda)',
            'periodicity_label' => 'Semestral', 'interval_days' => 180, 'is_critical' => true,
        ]);
    }

    /**
     * Anual -- inspeção formal completa + calibração dos dispositivos de
     * segurança, exigência de registro documentado NR-12.
     */
    private function seedAnnual(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Inspeção anual formal do guindaste (laudo técnico com ART)',
            'periodicity_label' => 'Anual', 'interval_days' => 365, 'is_critical' => true,
            'notes' => 'Plano de manutenção preventiva documentado por hora-máquina ou calendário -- exigência NR-12.',
        ]);
        $this->item($family, [
            'name' => 'Calibração do limitador de carga e demais dispositivos de segurança',
            'periodicity_label' => 'Anual', 'interval_days' => 365, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Substituição preventiva de mangueiras hidráulicas de alta pressão',
            'periodicity_label' => 'Anual', 'interval_days' => 365,
        ]);
    }

    /**
     * Checklist de inspeção diária (C/NC/NA), estrutura em seções -- mesmo
     * padrão dos demais segmentos.
     */
    private function seedInspectionChecklist(PmpEquipmentFamily $family): void
    {
        $sortOrder = 1;

        // Seção: Documentação (NR-12 exige manual/manutenção a bordo)
        $this->checklistItem($family, 'Documentação', 'Manual de operação e manutenção em português disponível na cabine', $sortOrder++);
        $this->checklistItem($family, 'Documentação', 'Placa de identificação legível (capacidade, fabricante, ano)', $sortOrder++);
        $this->checklistItem($family, 'Documentação', 'Livro de manutenção atualizado (óleos, filtros, cabos)', $sortOrder++);
        $this->checklistItem($family, 'Documentação', 'Registro do último ensaio não destrutivo de gancho e cabo', $sortOrder++);

        // Seção: Sistema Hidráulico
        $this->checklistItem($family, 'Sistema Hidráulico', 'Vazamentos em cilindros, mangueiras e conexões', $sortOrder++);
        $this->checklistItem($family, 'Sistema Hidráulico', 'Nível de óleo hidráulico dentro da faixa', $sortOrder++);
        $this->checklistItem($family, 'Sistema Hidráulico', 'Funcionamento suave dos movimentos (sem trepidação)', $sortOrder++);

        // Seção: Estrutura e Lança
        $this->checklistItem($family, 'Estrutura e Lança', 'Gancho sem trincas/deformação, trava de segurança funcional', $sortOrder++);
        $this->checklistItem($family, 'Estrutura e Lança', 'Cabo de aço sem fios rompidos além do critério de descarte', $sortOrder++);
        $this->checklistItem($family, 'Estrutura e Lança', 'Estrutura da lança sem trincas ou deformações visíveis', $sortOrder++);
        $this->checklistItem($family, 'Estrutura e Lança', 'Estabilizadores/sapatas com extensão e travamento corretos', $sortOrder++);

        // Seção: Dispositivos de Segurança
        $this->checklistItem($family, 'Dispositivos de Segurança', 'Limitador de carga funcional', $sortOrder++);
        $this->checklistItem($family, 'Dispositivos de Segurança', 'Fim de curso funcional', $sortOrder++);
        $this->checklistItem($family, 'Dispositivos de Segurança', 'Alarme sonoro de içamento funcional', $sortOrder++);

        // Seção: Veículo Base (chassi do caminhão)
        $this->checklistItem($family, 'Veículo Base', 'Freios, luzes e retrovisores em condições normais', $sortOrder++);
        $this->checklistItem($family, 'Veículo Base', 'Nível de água do radiador e óleo do motor', $sortOrder++);
    }
}
