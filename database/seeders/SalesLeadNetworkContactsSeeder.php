<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contatos de WhatsApp de network antigo do usuário (relacionamento
 * pessoal/profissional prévio, não prospecção fria) -- source=indicacao,
 * diferente dos seeders SalesLeadRegionalRenters e SalesLeadIndustrialProspects
 * (esses sim source=prospeccao_ativa). Nome da pessoa de contato vai no
 * repeater decision_makers (não há campo de nome solto no schema). Mesmo
 * padrão idempotente dos seeders anteriores: update se já existe (por
 * company_name), create se não.
 */
class SalesLeadNetworkContactsSeeder extends Seeder
{
    public function run(): void
    {
        $assignedUserId = User::where('email', 'humberto@oravel.com.br')->value('id');

        $contacts = [
            ['name' => 'Alex', 'company_name' => 'Muqmaq', 'phone' => '19993687418'],
            ['name' => 'Bruno', 'company_name' => 'Multmaquinas', 'phone' => '19992010086'],
            ['name' => 'Andre', 'company_name' => 'Eleva Plataformas', 'phone' => '11940009573'],
            ['name' => 'Cleide', 'company_name' => 'Locação DG Arantes', 'phone' => '19994940215'],
            ['name' => 'Robson', 'company_name' => 'WGL', 'phone' => '19991955458'],
            ['name' => 'Victor', 'company_name' => 'Vai Locar', 'phone' => '11917514990'],
            ['name' => 'Walkiria', 'company_name' => 'Ativos Loca', 'phone' => '19971631676'],
        ];

        foreach ($contacts as $contact) {
            $companyName = $contact['company_name'];
            $decisionMakers = [['name' => $contact['name'], 'role' => null]];

            $existing = SalesLead::where('company_name', $companyName)->first();

            if ($existing) {
                $existing->update([
                    'phone' => $contact['phone'],
                    'decision_makers' => $decisionMakers,
                ]);

                continue;
            }

            SalesLead::create([
                'company_name' => $companyName,
                'phone' => $contact['phone'],
                'decision_makers' => $decisionMakers,
                'segment' => Client::NICHE_LOCACAO_EQUIPAMENTOS,
                'source' => SalesLead::SOURCE_INDICACAO,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'assigned_user_id' => $assignedUserId,
            ]);
        }
    }
}
