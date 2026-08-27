<?php

namespace Database\Seeders;

use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Catalogo global (painel central, sem tenant_id) pro segmento
 * 'solda_corte' -- conteudo real fornecido pelo usuario 2026-08-27,
 * focado em maquinas de solda, corte plasma, motogeradores e automacao
 * industrial, alinhado a ISO 9001 e NR-10/NR-12/NR-18. Documento separa
 * por familia tecnologica -- 4 familias, mesmo padrao dos segmentos
 * anteriores.
 *
 * Checklist de inspecao NAO foi fornecido junto -- seedInspectionChecklist()
 * fica pendente, adicionar quando o usuario enviar (mesmo padrao de
 * PmpAerialPlatformFamilySeeder, que recebeu o checklist em mensagem
 * separada).
 *
 * Idempotente por nome: mesmo padrao dos outros seeders de PMP.
 *
 * Uso: php artisan db:seed --class=PmpWeldingFamilySeeder
 */
class PmpWeldingFamilySeeder extends Seeder
{
    private const SEGMENT = 'solda_corte';

    public function run(): void
    {
        $this->seedInversoresFontesSolda();
        $this->seedCortePlasma();
        $this->seedMecanizacaoAutomacao();
        $this->seedMotossoldadoras();

        $this->command?->info('Catálogo PMP "Solda & Corte": 4 famílias semeadas/atualizadas (checklist de inspeção pendente).');
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

    /**
     * Rotina diária comum a equipamentos eletrônicos de solda/corte
     * (item A do documento), sem a parte de motossoldadora a combustão.
     */
    private function seedDailyChecklist(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Inspeção visual de capas de cabos de solda, garras de massa e conectores rápidos (cortes/queimaduras)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Desgaste do bico de contato, bocal e isolador das tochas',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $this->item($family, [
            'name' => 'Teste de continuidade do aterramento de proteção do chassi da máquina',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
    }

    /**
     * Manutenção mensal (250h) — equipamentos eletrônicos de solda/corte
     * (item B.1 do documento), comum às famílias de inversores/plasma.
     */
    private function seedMonthlyElectronic(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Higienização interna: sopragem de poeira metálica sobre dissipadores e placas eletrônicas',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
            'notes' => 'Ar comprimido seco (pressão reduzida) ou aspirador antiestático.',
        ]);
        $this->item($family, [
            'name' => 'Nível do líquido de arrefecimento neutro e funcionamento do fluxo da bomba (tochas refrigeradas a água)',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
    }

    /**
     * Manutenção semestral/anual (1000h) — calibração ISO 9001/IEC
     * 60974-14 e rigidez dielétrica NR-10, comum a todas as famílias
     * elétricas de solda/corte.
     */
    private function seedAnnualCalibration(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Medição das saídas de corrente e tensão reais vs. mostrador digital, com banco de carga resistiva',
            'periodicity_label' => '1000h / Anual (ISO 9001 / IEC 60974-14)', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Emitir certificado de calibração pra garantir repetibilidade nos processos de soldagem dos clientes.',
        ]);
        $this->item($family, [
            'name' => 'Ensaio de rigidez dielétrica (megômetro) entre primário e secundário',
            'periodicity_label' => '1000h / Anual (NR-10)', 'interval_hours' => 1000, 'is_critical' => true,
        ]);
    }

    private function seedInversoresFontesSolda(): void
    {
        $family = $this->family(
            'Inversores & Fontes de Solda',
            'MIG/MAG, TIG, Eletrodo Revestido e Arco Submerso -- placas eletrônicas de potência, conectores de engate rápido, sistema de refrigeração da tocha e calibração de corrente/tensão.'
        );

        $this->seedDailyChecklist($family);
        $this->seedMonthlyElectronic($family);
        $this->seedAnnualCalibration($family);
    }

    private function seedCortePlasma(): void
    {
        $family = $this->family(
            'Sistemas de Corte Plasma',
            'Unidades Inversoras / Módulos de Corte Plasma -- consumíveis da tocha (bico, eletrodo, difusor), pureza/filtragem do ar comprimido e circuito elétrico de alta frequência.'
        );

        $this->seedDailyChecklist($family);
        $this->seedMonthlyElectronic($family);
        $this->item($family, [
            'name' => 'Verificação e substituição dos elementos filtrantes e drenagem de umidade na entrada da fonte',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->seedAnnualCalibration($family);
    }

    private function seedMecanizacaoAutomacao(): void
    {
        $family = $this->family(
            'Mecanização & Automação',
            'Tartarugas de corte, alimentadores de arame, posicionadores -- motores de passo/DC, engrenagens, trilhos de deslocamento e cabos de comando (interconnects).'
        );

        $this->item($family, [
            'name' => 'Inspeção do canal roletado (roldanas de tração), guia de teflon/aço e ajuste de pressão no arame',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $this->item($family, [
            'name' => 'Substituição das roldanas de tração desgastadas e guias de arame',
            'periodicity_label' => '1000h / Semestral-Anual', 'interval_hours' => 1000, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção de escovas dos motores DC dos alimentadores e carros de corte',
            'periodicity_label' => '1000h / Semestral-Anual', 'interval_hours' => 1000,
        ]);
    }

    private function seedMotossoldadoras(): void
    {
        $family = $this->family(
            'Motossoldadoras & Geradores',
            'Unidades autônomas a combustão (Diesel/Gasolina) -- motor a combustão, alternador de solda/geração, filtros de combustível e baterias de partida.'
        );

        $this->seedDailyChecklist($family);
        $this->item($family, [
            'name' => 'Troca do óleo do motor, filtro de óleo e filtro de combustível',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Limpeza de bornes e teste de carga da bateria',
            'periodicity_label' => '250h / Mensal', 'interval_hours' => 250,
        ]);
        $this->seedAnnualCalibration($family);
    }
}
