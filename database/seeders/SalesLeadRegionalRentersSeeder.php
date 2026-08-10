<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 20 locadoras regionais de medio porte (guindaste/munck, empilhadeira,
 * plataforma, gerador, compressor), levantadas via prospeccao ativa em
 * 2026-08 -- validadas com site institucional proprio (nao so' base de
 * CNPJ) e contato extraido da fonte primaria, nunca inventado. Mesmo
 * padrao idempotente de SalesLeadEquipmentRentalOutreachSeeder: update se
 * ja existe (por company_name), create se nao. Nenhuma sobreposicao com os
 * 17 leads ja cadastrados em SalesLeadEquipmentRentalOutreachSeeder /
 * SalesLeadIndustrialProspectsSeeder (conferido antes de escrever este
 * seeder).
 */
class SalesLeadRegionalRentersSeeder extends Seeder
{
    public function run(): void
    {
        $assignedUserId = User::where('email', 'humberto@oravel.com.br')->value('id');

        $prospects = [
            [
                'company_name' => 'Silva Radar',
                'website' => 'silvaradar.com.br',
                'phone' => '1122292989',
                'email' => 'comercial@silvaradar.com.br',
                'city' => 'Guarulhos',
                'uf' => 'SP',
                'critical_pain' => 'Quase 70 anos de frota heterogênea (guindaste articulado, telescópico, empilhadeira, carreta) sem histórico de manutenção centralizado por tipo de equipamento.',
                'oravel_solution' => 'Histórico digital único por ativo, independente do tipo de equipamento, com plano de manutenção próprio para cada categoria de frota.',
            ],
            [
                'company_name' => 'MunckMaq (Durães & Durães)',
                'website' => 'munckmaq.com.br',
                'phone' => '1932790005',
                'email' => 'contato@duraeslocacoes.com.br',
                'city' => 'Campinas',
                'uf' => 'SP',
                'critical_pain' => 'Operação em duas praças (Campinas/SP e Catalão/GO) com guindaste, munck e plataforma sem visibilidade centralizada de disponibilidade entre filiais.',
                'oravel_solution' => 'Painel único de disponibilidade de frota multi-filial, evita conflito de alocação entre unidades.',
            ],
            [
                'company_name' => 'Pracima Guindastes',
                'website' => 'pracimaguindastes.com.br',
                'phone' => '7730221339',
                'email' => 'contato@pracimaguindastes.com.br',
                'city' => 'Luís Eduardo Magalhães',
                'uf' => 'BA',
                'critical_pain' => 'Atuação pulverizada em Norte/Nordeste/Centro-Oeste com frota de 35 a 100t — falta de rastreio de preventiva por unidade remota gera parada não planejada longe da base.',
                'oravel_solution' => 'Alerta de manutenção por horímetro acessível de qualquer unidade, antecipa parada antes do equipamento ficar indisponível longe da base.',
            ],
            [
                'company_name' => 'Guindasloc',
                'website' => 'guindasloc.com.br',
                'phone' => '1936813055',
                'email' => 'comercial@guindasloc.com.br',
                'city' => 'São José do Rio Pardo',
                'uf' => 'SP',
                'critical_pain' => 'Negócio diversificado (locação de guindaste + transporte pesado + desmontagem industrial) sem sistema único cobrindo manutenção de frota e ordens de serviço de campo.',
                'oravel_solution' => 'Ordem de serviço e manutenção de frota no mesmo sistema, sem depender de controle paralelo por linha de negócio.',
            ],
            [
                'company_name' => 'Sumaq Guindastes',
                'website' => 'sumaqguindastes.com.br',
                'phone' => '1938641313',
                'email' => 'sumaq@sumaqguindastes.com.br',
                'city' => 'Sumaré',
                'uf' => 'SP',
                'critical_pain' => 'Frota de guindastes de grande porte (25 a 300t, Grove/Terex Demag/Tadano) fundada em 1982 — equipamento de altíssimo valor com risco real de ainda depender de controle manual/planilha para preventiva.',
                'oravel_solution' => 'Plano de manutenção preventiva estruturado por horímetro, essencial pra frota de alto valor onde falha custa caro.',
            ],
            [
                'company_name' => 'Ceará Munck',
                'website' => 'cearamunck.com.br',
                'phone' => '8532751632',
                'email' => 'cearamunck@hotmail.com',
                'city' => 'Itaitinga',
                'uf' => 'CE',
                'critical_pain' => 'Operação ainda muito manual/informal (e-mail em domínio pessoal) — checklist de entrega/devolução provavelmente em papel, gerando disputa com cliente sobre avaria.',
                'oravel_solution' => 'Checklist digital com foto na entrega e devolução, prova de estado do equipamento sem depender de papel.',
            ],
            [
                'company_name' => 'Meta Guindastes',
                'website' => 'metaguindastes.com.br',
                'phone' => '62999886850',
                'email' => 'metaguindastes@gmail.com',
                'city' => 'Goiânia',
                'uf' => 'GO',
                'critical_pain' => 'Portfólio muito diversificado (munck, cesto aéreo, contêiner, placa solar) com atendimento 24h anunciado, alto risco de despachar equipe sem visibilidade real de disponibilidade de frota.',
                'oravel_solution' => 'Visibilidade de disponibilidade em tempo real antes de despachar equipe, evita deslocamento pra equipamento já comprometido.',
            ],
            [
                'company_name' => 'JK Empilhadeiras',
                'website' => 'empilhadeirasjk.com.br',
                'phone' => '11922410243',
                'email' => 'contato@empilhadeirasjk.com.br',
                'city' => 'Várzea Paulista',
                'uf' => 'SP',
                'critical_pain' => 'Oficina própria de manutenção e retrofit com controle de ordem de serviço e peças ainda manual/planilha, sem rastreabilidade formal por equipamento locado.',
                'oravel_solution' => 'Ordem de serviço digital com controle de peças vinculado ao histórico de cada empilhadeira.',
            ],
            [
                'company_name' => 'JP Empilhadeiras',
                'website' => 'jpempilhadeiras.com.br',
                'phone' => '1147826622',
                'email' => 'atendimento@jpempilhadeiras.com.br',
                'city' => 'Embu das Artes',
                'uf' => 'SP',
                'critical_pain' => 'Frota multimarca (Toyota, Hyster, Yale, Komatsu, Still, Linde) — sem sistema único, difícil padronizar plano de manutenção preventiva entre fabricantes tão diferentes.',
                'oravel_solution' => 'Plano de manutenção preventiva padronizado por ativo, independente da marca do equipamento.',
            ],
            [
                'company_name' => 'Grupo APC',
                'website' => 'grupoapc.com.br',
                'phone' => '08000408003',
                'city' => 'Campinas',
                'uf' => 'SP',
                'critical_pain' => 'Mais de 120 modelos de plataforma elétrica/diesel/ultra boom em 12 pontos de referência — alta chance de checklist de saída/retorno informal, gerando disputa sobre avaria com cliente.',
                'oravel_solution' => 'Checklist digital de saída/retorno com foto obrigatória, prova de estado pra cada modelo de plataforma.',
            ],
            [
                'company_name' => 'Locall Locadora',
                'website' => 'locadoralocall.com.br',
                'phone' => '4833801795',
                'email' => 'comercial@locadoralocall.com.br',
                'decision_makers' => [['name' => 'Leonardo Fabiani', 'role' => 'CEO']],
                'city' => 'São José',
                'uf' => 'SC',
                'critical_pain' => 'Operação multi-filial em 4 estados (SC, PR, MS + postos avançados SP/RS) — coordenação de preventiva e disponibilidade entre 5+ unidades dispersas hoje sem sistema centralizado.',
                'oravel_solution' => 'Gestão de manutenção e disponibilidade centralizada entre todas as unidades, com visão única pro CEO.',
            ],
            [
                'company_name' => 'Locamavi Plataformas',
                'website' => 'plataformaslocamavi.com.br',
                'phone' => '11955308470',
                'email' => 'contato@plataformaslocamavi.com.br',
                'city' => 'Paulínia',
                'uf' => 'SP',
                'critical_pain' => 'Frota de 98+ equipamentos de 5 marcas (JLG, Genie, Haulotte, Skyjack, Dingli) com alto volume de entrega/retirada diária na Grande SP — terreno fértil pra checklist informal e falta de disponibilidade em tempo real.',
                'oravel_solution' => 'Checklist mobile de entrega/devolução via celular, agilidade no giro alto de entrada/saída sem perder rastreabilidade.',
            ],
            [
                'company_name' => 'Bermaq Brasil',
                'website' => 'bermaqbrasil.com.br',
                'phone' => '1933841200',
                'email' => 'comercial@bermaq.com.br',
                'city' => 'Campinas',
                'uf' => 'SP',
                'critical_pain' => 'Modelo combina locação + venda + treinamento, multimarca (Genie, SkyJack, JLG, Manitou) — gestão de manutenção provavelmente segmentada por marca/fornecedor, sem visão unificada da frota alugada.',
                'oravel_solution' => 'Visão unificada de frota alugada independente da marca/fornecedor de origem do equipamento.',
            ],
            [
                'company_name' => 'ABC Geradores',
                'website' => 'abcgeradores.com.br',
                'phone' => '1123557802',
                'email' => 'contato@abcgeradores.com.br',
                'city' => 'São Bernardo do Campo',
                'uf' => 'SP',
                'critical_pain' => 'Negócio multi-linha (gerador + plataforma) aumenta complexidade de gestão de disponibilidade em tempo real entre categorias distintas — locação emergencial de gerador é tipicamente crítica em tempo.',
                'oravel_solution' => 'Disponibilidade de frota em tempo real por categoria de equipamento, essencial pra resposta rápida em locação emergencial.',
            ],
            [
                'company_name' => 'Power Brasil Geradores',
                'website' => 'powerbrasilgeradores.com.br',
                'phone' => '1143681882',
                'city' => 'São Bernardo do Campo',
                'uf' => 'SP',
                'critical_pain' => 'Mais de 600 clientes nos 27 estados com suporte emergencial 24h — escala nacional com atendimento de curto prazo exige visibilidade em tempo real de disponibilidade de frota pra responder chamados espalhados pelo país.',
                'oravel_solution' => 'Visibilidade nacional de disponibilidade de frota, resposta rápida a chamado emergencial em qualquer estado.',
            ],
            [
                'company_name' => 'Minas Ar Compressores',
                'website' => 'minasarcompressores.com.br',
                'phone' => '3125510021',
                'email' => 'comercial@minasarcompressores.com.br',
                'city' => 'Belo Horizonte',
                'uf' => 'MG',
                'critical_pain' => 'Modelo combina venda + locação + assistência técnica 24h — falta de integração entre ordem de serviço de campo emergencial e controle de ativos alugados.',
                'oravel_solution' => 'Ordem de serviço de campo integrada ao controle de ativos alugados, sem sistema paralelo pro atendimento emergencial.',
            ],
            [
                'company_name' => 'Ar Brasil Compressores',
                'website' => 'arbrasilcompressores.com.br',
                'phone' => '1139048882',
                'city' => 'Barueri',
                'uf' => 'SP',
                'critical_pain' => '36 anos de mercado com portfólio multimarca (Tewatt, Coaire, Elgi, Atlas, Schulz) em venda+locação+manutenção 24h — perfil clássico de empresa madura ainda em planilha pro histórico por cliente/equipamento.',
                'oravel_solution' => 'Histórico de manutenção por cliente e por equipamento em um só sistema, substitui controle em planilha.',
            ],
            [
                'company_name' => 'JA Compressores',
                'website' => 'jacompressores.com.br',
                'phone' => '1123866014',
                'city' => 'São Paulo',
                'uf' => 'SP',
                'critical_pain' => 'Atende indústria plástica com equipamentos de 4HP a 300HP — variação grande de porte por cliente é candidata a plano de preventiva diferenciado que hoje provavelmente não é sistematizado.',
                'oravel_solution' => 'Plano de manutenção preventiva configurável por porte/modelo de equipamento, não um padrão único genérico.',
            ],
            [
                'company_name' => 'Consenso Compressores',
                'website' => 'consensocompressores.com.br',
                'phone' => '1143533595',
                'email' => 'contato@consenso.com.br',
                'city' => 'São Bernardo do Campo',
                'uf' => 'SP',
                'critical_pain' => '40 anos de mercado, representa marcas internacionais (Sullair, Chicago Pneumatic) em locação+manutenção+revisão — múltiplas frentes de serviço sem integração central de dados de manutenção.',
                'oravel_solution' => 'Dados de manutenção centralizados entre todas as frentes de serviço (locação, revisão, manutenção).',
            ],
            [
                'company_name' => 'Airloc Compressores',
                'website' => 'airloc.com.br',
                'phone' => '1144451090',
                'email' => 'contato@airloc.com.br',
                'city' => 'Caieiras',
                'uf' => 'SP',
                'critical_pain' => 'Suporte técnico 24/7 com frota multimarca (Atlas Copco, Chiaperini, Kaeser, Schulz, Ingersoll) atendendo ABC/Guarulhos/Osasco — atendimento emergencial recorrente sem visibilidade de disponibilidade por região é risco real de atraso a cliente crítico.',
                'oravel_solution' => 'Disponibilidade de frota por região visível em tempo real, evita atraso em atendimento emergencial.',
            ],
        ];

        foreach ($prospects as $data) {
            $companyName = $data['company_name'];
            unset($data['company_name']);

            $existing = SalesLead::where('company_name', $companyName)->first();

            if ($existing) {
                $existing->update($data);

                continue;
            }

            SalesLead::create(array_merge($data, [
                'company_name' => $companyName,
                'segment' => Client::NICHE_LOCACAO_EQUIPAMENTOS,
                'source' => SalesLead::SOURCE_PROSPECCAO_ATIVA,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'assigned_user_id' => $assignedUserId,
            ]));
        }
    }
}
