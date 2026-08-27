<?php

namespace Database\Seeders;

use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateItem;
use Illuminate\Database\Seeder;

/**
 * Catalogo global (painel central, sem tenant_id) de familias de
 * equipamento pra templates de PMP -- conteudo real fornecido pelo
 * usuario (diretor da Eletrac Empilhadeiras) 2026-08-26, cobrindo
 * portfolio multimarcas (Still/Hyster/Yale/Toyota), fabricacao propria
 * (Skam), baterias chumbo-acido e litio, e plataformas elevatorias.
 *
 * Idempotente por nome: PmpEquipmentFamily::firstOrCreate por
 * segment+name, PmpTemplateItem::firstOrCreate por family_id+name.
 *
 * Uso: php artisan db:seed --class=PmpEquipmentFamilySeeder
 */
class PmpEquipmentFamilySeeder extends Seeder
{
    private const SEGMENT = 'empilhadeiras';

    public function run(): void
    {
        $this->seedEletricosLeves();
        $this->seedEletricosPesados();
        $this->seedCombustao();
        $this->seedTrabalhoEmAltura();
        $this->seedSistemasDeEnergia();

        $this->command?->info('Catálogo PMP "Empilhadeiras": 5 famílias semeadas/atualizadas.');
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
                'notes' => $data['notes'] ?? null,
            ],
        );
    }

    private function seedEletricosLeves(): void
    {
        $family = $this->family(
            'Elétricos Leves / Modulares',
            'Transpaleteiras e Empilhadeiras Patoladas (Skam / Eletrac) -- sistema elétrico de tração, leme/timão, roletes de apoio, microinterruptores.'
        );

        $this->seedDailyChecklist($family);

        $this->item($family, [
            'name' => 'Sopragem dos módulos inversores de frequência/controladores',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Ar comprimido seco; verificar aperto dos bornes da placa eletrônica.',
        ]);
        $this->item($family, [
            'name' => 'Medição de desgaste do disco do freio eletromagnético',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
            'notes' => 'Regulagem da folga (gap).',
        ]);
        $this->item($family, [
            'name' => 'Limpeza e lubrificação da torre de elevação e correntes',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Verificar alongamento das correntes; graxa adesiva apropriada, evitar excesso em áreas operacionais limpas.',
        ]);
        $this->item($family, [
            'name' => 'Alinhamento da roda motriz e ajuste das rodas de apoio/estabilizadoras',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
        ]);

        $this->seedAdvanced500h($family);
        $this->seedGeneral1000h($family);
    }

    private function seedEletricosPesados(): void
    {
        $family = $this->family(
            'Elétricos Pesados & Retráteis',
            'Empilhadeiras Retráteis, Trilaterais (Still, Skam, Toyota) -- torre (Duplex/Triplex), cilindros de elevação, sensores de altura, frenagem eletrônica.'
        );

        $this->seedDailyChecklist($family);

        $this->item($family, [
            'name' => 'Sopragem dos módulos inversores de frequência/controladores',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Ar comprimido seco; verificar aperto dos bornes da placa eletrônica.',
        ]);
        $this->item($family, [
            'name' => 'Medição de desgaste do disco do freio eletromagnético',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
            'notes' => 'Regulagem da folga (gap).',
        ]);
        $this->item($family, [
            'name' => 'Limpeza e lubrificação da torre de elevação e correntes',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Verificar alongamento das correntes; graxa adesiva apropriada, evitar excesso em áreas operacionais limpas.',
        ]);
        $this->item($family, [
            'name' => 'Medição da corrente de pico dos motores de tração/elevação',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500,
            'notes' => 'Inspeção de desgaste das escovas (motores CC mais antigos).',
        ]);

        $this->seedAdvanced500h($family);
        $this->seedGeneral1000h($family);
    }

    private function seedCombustao(): void
    {
        $family = $this->family(
            'Equipamentos a Combustão',
            'Empilhadeiras GLP / Diesel (Hyster, Yale, Toyota) -- motor a combustão, sistema de arrefecimento, transmissão hidráulica, exaustão.'
        );

        $this->seedDailyChecklist($family);

        $this->item($family, [
            'name' => 'Troca do óleo do motor e do filtro de óleo',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção/limpeza do filtro de ar',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
        ]);
        $this->item($family, [
            'name' => 'Checagem de mangueiras de GLP e estanqueidade dos conectores',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
            'notes' => 'Regulagem do misturador/carburador.',
        ]);
        $this->item($family, [
            'name' => 'Limpeza externa do radiador',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Remoção de poeira e fuligem; inspeção da tensão da correia do alternador.',
        ]);

        $this->seedAdvanced500h($family);
        $this->seedGeneral1000h($family);
    }

    private function seedTrabalhoEmAltura(): void
    {
        $family = $this->family(
            'Trabalho em Altura',
            'Plataformas Elevatórias (Tesouras e Articuladas) -- sistema hidráulico de elevação, sensores de inclinação/pressão, nivelamento, travas de segurança.'
        );

        $this->seedDailyChecklist($family);

        $this->item($family, [
            'name' => 'Teste do sistema de desço de emergência (válvula manual de descida)',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Inspeção estrutural das buchas das tesouras (mecanismo pantográfico) e pinos de articulação',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Teste dos sensores de limite de carga e inclinação lateral',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Substituição do filtro de retorno do óleo hidráulico',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500,
        ]);
        $this->item($family, [
            'name' => 'Inspeção das mangueiras de alta pressão da plataforma',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500, 'is_critical' => true,
            'notes' => 'Verificação de ressecamento e deformações.',
        ]);
        $this->item($family, [
            'name' => 'Medição de pressão da bomba hidráulica com manômetro calibrado',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500,
        ]);

        $this->seedGeneral1000h($family);
    }

    private function seedSistemasDeEnergia(): void
    {
        $family = $this->family(
            'Sistemas de Energia',
            'Baterias Chumbo-Ácido vs. Baterias de Lítio -- densidade da solução/nível de água (Chumbo) vs. BMS, conexões do módulo de carga rápida (Lítio).'
        );

        // Baterias convencionais (chumbo-ácido tracionárias)
        $this->item($family, [
            'name' => 'Adição de água desmineralizada após o término da carga',
            'periodicity_label' => 'Semanal', 'interval_days' => 7, 'is_critical' => true,
            'notes' => 'Nunca antes da carga.',
        ]);
        $this->item($family, [
            'name' => 'Ciclo de equalização da bateria chumbo-ácido',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Conforme manual, pra eliminar sulfatação das placas.',
        ]);
        $this->item($family, [
            'name' => 'Higienização/lavagem superior da bateria chumbo-ácido',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Solução neutralizadora, pra evitar fuga de corrente para o chassi.',
        ]);

        // Baterias de lítio
        $this->item($family, [
            'name' => 'Leitura de relatórios de falha do BMS (Battery Management System)',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250, 'is_critical' => true,
            'notes' => 'Porta de diagnóstico -- temperatura de células, desbalanceamento de voltagem.',
        ]);
        $this->item($family, [
            'name' => 'Limpeza dos conectores de carga rápida (bateria de lítio)',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
            'notes' => 'Aplicação de limpador de contatos nos pinos da tomada de alta corrente.',
        ]);
        $this->item($family, [
            'name' => 'Atualização de firmware do gerenciador de carga',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500,
            'notes' => 'Verificação e atualização do software; comunicação CAN entre bateria e empilhadeira.',
        ]);

        $this->seedGeneral1000h($family);
    }

    private function seedDailyChecklist(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Conexão dos cabos de força e travamento do banco da bateria',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Teste de resposta do freio de emergência (botão de umbigo/reversão) e retorno do joystick',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Teste de subida e descida livre da torre/garfos, sem carga e em altura mínima',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        $this->item($family, [
            'name' => 'Verificação de desgaste/trincas nas rodas (poliuretano ou pneus superelásticos)',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $this->item($family, [
            'name' => 'Inspeção visual de vazamentos (óleo hidráulico ou fluido de arrefecimento)',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
    }

    private function seedAdvanced500h(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Substituição do filtro de retorno do óleo hidráulico',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500,
        ]);
        $this->item($family, [
            'name' => 'Inspeção das mangueiras de alta pressão da torre',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500, 'is_critical' => true,
            'notes' => 'Verificação de ressecamento e deformações.',
        ]);
        $this->item($family, [
            'name' => 'Medição de pressão da bomba hidráulica com manômetro calibrado',
            'periodicity_label' => '500-600h / Trimestral', 'interval_hours' => 500,
        ]);
    }

    private function seedGeneral1000h(PmpEquipmentFamily $family): void
    {
        $this->item($family, [
            'name' => 'Substituição integral do óleo hidráulico de todo o circuito',
            'periodicity_label' => '1000-2000h / Anual', 'interval_hours' => 1000,
            'notes' => 'Reservatório, bombas e cilindros.',
        ]);
        $this->item($family, [
            'name' => 'Varredura termográfica (quadro elétrico, conectores de bateria, motores sob carga)',
            'periodicity_label' => '1000-2000h / Anual', 'interval_hours' => 1000, 'is_critical' => true,
            'notes' => 'Identificar pontos de superaquecimento por mau contato.',
        ]);
        $this->item($family, [
            'name' => 'Troca do óleo da caixa de redução/diferencial',
            'periodicity_label' => '1000-2000h / Anual', 'interval_hours' => 1000,
        ]);
        $this->item($family, [
            'name' => 'Ensaio não destrutivo (líquido penetrante ou ultrassom) em garfos, chassi e suporte da corrente',
            'periodicity_label' => '1000-2000h / Anual', 'interval_hours' => 2000, 'is_critical' => true,
        ]);
    }
}
