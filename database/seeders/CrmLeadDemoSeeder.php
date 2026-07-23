<?php

namespace Database\Seeders;

use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\CrmLeadInteraction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Popula o CRM de prospecção do PRÓPRIO tenant (App\Models\CrmLead --
 * diferente do SalesLead usado no painel Central pra Oravel prospectar
 * tenants novos) -- estava com zero dados nos 5 tenants de demonstração,
 * mesmo o Resource/funil/mapa/agenda já existindo prontos. Idempotente,
 * aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=CrmLeadDemoSeeder
 */
class CrmLeadDemoSeeder extends Seeder
{
    /**
     * Prospects fictícios por tenant -- empresas que PRECISARIAM alugar o
     * equipamento daquele tenant, no mesmo nicho/região (Campinas-SP) já
     * estabelecido pelos seeders de demo originais.
     */
    private const LEADS_BY_SLUG = [
        'torres-guindastes' => [
            ['name' => 'Ricardo Bittencourt', 'company_name' => 'Engebras Construções', 'segment' => CrmLead::SEGMENT_CONSTRUCAO, 'stage' => CrmLead::STAGE_QUALIFICADO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Campinas', 'equipment_interest' => 'Guindaste 100t pra montagem de estrutura metálica'],
            ['name' => 'Fernanda Aquino', 'company_name' => 'Aquino Engenharia e Montagens', 'segment' => CrmLead::SEGMENT_INDUSTRIA, 'stage' => CrmLead::STAGE_CONTATO_INICIADO, 'source' => CrmLead::SOURCE_SITE, 'city' => 'Sumaré', 'equipment_interest' => 'Munck pra descarga de equipamento pesado'],
            ['name' => 'Otávio Lemes', 'company_name' => 'Lemes Torres de Telecom', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_NOVO, 'source' => CrmLead::SOURCE_CONTATO_FRIO, 'city' => 'Campinas'],
            ['name' => 'Bianca Ferraz', 'company_name' => 'Ferraz Estruturas Pré-Moldadas', 'segment' => CrmLead::SEGMENT_CONSTRUCAO, 'stage' => CrmLead::STAGE_CONVERTIDO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Hortolândia'],
            ['name' => 'Gustavo Nery', 'company_name' => 'Nery Guindastes Ltda (concorrente pesquisando preço)', 'segment' => CrmLead::SEGMENT_OUTROS, 'stage' => CrmLead::STAGE_PERDIDO, 'source' => CrmLead::SOURCE_EVENTO, 'lost_reason_category' => CrmLead::LOST_REASON_CONCORRENCIA, 'lost_reason' => 'Fechou com fornecedor local mais barato.'],
        ],
        'geradores-rmc' => [
            ['name' => 'Camila Torquato', 'company_name' => 'DataSafe Data Centers', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_QUALIFICADO, 'source' => CrmLead::SOURCE_SITE, 'city' => 'Campinas', 'equipment_interest' => 'Gerador 350kVA pra backup de data center, contrato anual'],
            ['name' => 'Heitor Salomão', 'company_name' => 'Metalúrgica Salomão', 'segment' => CrmLead::SEGMENT_INDUSTRIA, 'stage' => CrmLead::STAGE_CONTATO_INICIADO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Paulínia'],
            ['name' => 'Renata Quintal', 'company_name' => 'Fazenda Quintal Agropecuária', 'segment' => CrmLead::SEGMENT_AGROPECUARIA, 'stage' => CrmLead::STAGE_NOVO, 'source' => CrmLead::SOURCE_CONTATO_FRIO, 'city' => 'Indaiatuba'],
            ['name' => 'Diego Fialho', 'company_name' => 'Condomínio Industrial Fialho', 'segment' => CrmLead::SEGMENT_COMERCIO, 'stage' => CrmLead::STAGE_CONVERTIDO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Campinas'],
        ],
        'construtora-alicerce-locacoes' => [
            ['name' => 'Patrícia Mourão', 'company_name' => 'Mourão Incorporadora', 'segment' => CrmLead::SEGMENT_CONSTRUCAO, 'stage' => CrmLead::STAGE_QUALIFICADO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Valinhos', 'equipment_interest' => 'Empilhadeira e plataforma elevatória, obra de 8 meses'],
            ['name' => 'Aline Bezerra', 'company_name' => 'Bezerra Reformas e Construção', 'segment' => CrmLead::SEGMENT_CONSTRUCAO, 'stage' => CrmLead::STAGE_NOVO, 'source' => CrmLead::SOURCE_SITE, 'city' => 'Campinas'],
            ['name' => 'Vinícius Prado', 'company_name' => 'Prado Empreendimentos', 'segment' => CrmLead::SEGMENT_CONSTRUCAO, 'stage' => CrmLead::STAGE_PERDIDO, 'source' => CrmLead::SOURCE_EVENTO, 'lost_reason_category' => CrmLead::LOST_REASON_SEM_ORCAMENTO, 'lost_reason' => 'Obra adiada pra o próximo ano fiscal.'],
        ],
        'eventos-show-geradores' => [
            ['name' => 'Juliana Marques', 'company_name' => 'Marques Produções de Eventos', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_QUALIFICADO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Campinas', 'equipment_interest' => 'Gerador silenciado pra casamento ao ar livre'],
            ['name' => 'Rodrigo Vasconcelos', 'company_name' => 'Vasconcelos Feiras e Exposições', 'segment' => CrmLead::SEGMENT_COMERCIO, 'stage' => CrmLead::STAGE_CONTATO_INICIADO, 'source' => CrmLead::SOURCE_SITE, 'city' => 'São Paulo'],
            ['name' => 'Tatiane Rangel', 'company_name' => 'Rangel Buffet e Eventos', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_NOVO, 'source' => CrmLead::SOURCE_CONTATO_FRIO, 'city' => 'Vinhedo'],
        ],
        'hospital-vida-plena-energia' => [
            ['name' => 'Dr. Márcio Junqueira', 'company_name' => 'Hospital São Rafael', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_QUALIFICADO, 'source' => CrmLead::SOURCE_INDICACAO, 'city' => 'Campinas', 'equipment_interest' => 'Backup de energia pra UTI Neonatal, redundância N+1'],
            ['name' => 'Dra. Isabela Freire', 'company_name' => 'Clínica Vida Freire', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_CONTATO_INICIADO, 'source' => CrmLead::SOURCE_EVENTO, 'city' => 'Americana'],
            ['name' => 'Eng. Paulo Cerqueira', 'company_name' => 'Laboratório Cerqueira Diagnósticos', 'segment' => CrmLead::SEGMENT_SERVICOS, 'stage' => CrmLead::STAGE_CONVERTIDO, 'source' => CrmLead::SOURCE_SITE, 'city' => 'Campinas'],
        ],
    ];

    public function run(): void
    {
        foreach (self::LEADS_BY_SLUG as $slug => $leads) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (CrmLead::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $comercial = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'Comercial'))
                ->first();
            $admin = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->first();

            if (! $admin && ! $comercial) {
                $this->command?->warn("Tenant '{$slug}' sem admin nem Comercial -- pulando.");

                continue;
            }

            // CrmLeadObserver só loga histórico de atribuição (CrmLeadAssignment)
            // dentro de um contexto autenticado real -- em console/seeder,
            // auth()->id() é null por padrão.
            Auth::login($admin ?? $comercial);

            foreach ($leads as $i => $data) {
                $lead = CrmLead::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'company_name' => $data['company_name'],
                    'segment' => $data['segment'],
                    'stage' => $data['stage'],
                    'source' => $data['source'],
                    'city' => $data['city'] ?? null,
                    'uf' => 'SP',
                    'phone' => '(19) 9'.random_int(1000, 9999).'-'.random_int(1000, 9999),
                    'equipment_interest' => $data['equipment_interest'] ?? null,
                    'lost_reason_category' => $data['lost_reason_category'] ?? null,
                    'lost_reason' => $data['lost_reason'] ?? null,
                    'estimated_value' => random_int(3, 40) * 1000,
                    'assigned_user_id' => $comercial?->id,
                ]);

                CrmLeadContact::create([
                    'tenant_id' => $tenant->id,
                    'crm_lead_id' => $lead->id,
                    'name' => $data['name'],
                    'role_title' => 'Responsável pela contratação',
                    'phone' => $lead->phone,
                    'email' => strtolower(str($data['name'])->slug('.')).'@'.strtolower(str($data['company_name'])->slug('')).'.com.br',
                    'is_primary' => true,
                ]);

                if (in_array($data['stage'], [CrmLead::STAGE_CONTATO_INICIADO, CrmLead::STAGE_QUALIFICADO, CrmLead::STAGE_CONVERTIDO], true)) {
                    CrmLeadInteraction::create([
                        'tenant_id' => $tenant->id,
                        'crm_lead_id' => $lead->id,
                        'user_id' => $comercial?->id,
                        'channel' => CrmLeadInteraction::CHANNEL_WHATSAPP,
                        'contact_date' => now()->subDays(random_int(2, 20)),
                        'summary' => 'Primeiro contato -- apresentado o portfólio e alinhado prazo de envio de orçamento.',
                        'next_action' => $data['stage'] === CrmLead::STAGE_CONVERTIDO ? null : 'Enviar orçamento formal.',
                        'next_followup_date' => $data['stage'] === CrmLead::STAGE_CONVERTIDO ? null : now()->addDays(random_int(2, 7)),
                        'stage_at_time' => $data['stage'],
                    ]);
                    $lead->refreshFollowUpCache();
                }

                // No primeiro lead de cada tenant, simula uma reatribuição
                // real (Comercial -> Admin) pra popular o histórico de
                // CrmLeadAssignment via Observer -- não é criado direto,
                // ver docblock do model.
                if ($i === 0 && $admin && $comercial && $admin->id !== $comercial->id) {
                    $lead->update(['assigned_user_id' => $admin->id]);
                }
            }
        }
    }
}
