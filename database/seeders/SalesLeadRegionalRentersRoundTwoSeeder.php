<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 2a leva de locadoras regionais de medio porte (guindaste/munck,
 * empilhadeira, plataforma, gerador, compressor), levantadas via
 * prospeccao ativa em 2026-08 -- validadas com site institucional proprio
 * e contato extraido da fonte primaria, nunca inventado. Cobre regioes que
 * a 1a leva (SalesLeadRegionalRentersSeeder) nao tinha: Norte, Nordeste,
 * Centro-Oeste, Sul. Mesmo padrao idempotente das levas anteriores: update
 * se ja existe (por company_name), create se nao. Sem sobreposicao com as
 * 37 empresas ja cadastradas nos seeders anteriores (conferido antes de
 * escrever este seeder).
 */
class SalesLeadRegionalRentersRoundTwoSeeder extends Seeder
{
    public function run(): void
    {
        $assignedUserId = User::where('email', 'humberto@oravel.com.br')->value('id');

        $prospects = [
            [
                'company_name' => 'VMG Locações de Muncks e Guindastes',
                'website' => 'vmgguindastes.com.br',
                'phone' => '1732236600',
                'city' => 'São José do Rio Preto',
                'uf' => 'SP',
                'critical_pain' => '25+ anos de mercado com 4 unidades entre SP e MS, frota multimarca de 25 a 600t — controle de manutenção provavelmente descentralizado entre filiais.',
                'oravel_solution' => 'Painel único de manutenção e disponibilidade entre todas as unidades, independente do porte do equipamento.',
            ],
            [
                'company_name' => 'Gerizim & Filhos Guindastes',
                'website' => 'gerizimtransportes.com.br',
                'phone' => '1129491605',
                'email' => 'gerizimtransp@hotmail.com',
                'decision_makers' => [['name' => 'Alfredo de Oliveira', 'role' => 'Fundador']],
                'city' => 'São Paulo',
                'uf' => 'SP',
                'critical_pain' => '35+ anos de mercado, operação em dois estados distantes (SP e MT) com promessa de "24h retorno garantido" — exige visibilidade de disponibilidade em tempo real pra sustentar o prazo.',
                'oravel_solution' => 'Disponibilidade de frota em tempo real entre unidades distantes, essencial pra cumprir prazo de retorno prometido.',
            ],
            [
                'company_name' => 'Companhia das Empilhadeiras',
                'website' => 'ciadasempilhadeiras.com.br',
                'phone' => '8134762000',
                'email' => 'comercial@ciadasempilhadeiras.com.br',
                'city' => 'Recife',
                'uf' => 'PE',
                'critical_pain' => '25+ anos de mercado com resposta técnica prometida em até 24h — volume de chamados provavelmente controlado em planilha, sem rastreabilidade formal.',
                'oravel_solution' => 'Ordem de serviço digital com prazo de atendimento rastreável, sustenta a promessa de resposta em 24h com dado real.',
            ],
            [
                'company_name' => 'Casa Forte Empilhadeiras',
                'website' => 'casaforteempilhadeiras.com.br',
                'phone' => '81995698381',
                'city' => 'Recife',
                'uf' => 'PE',
                'critical_pain' => 'Frota reserva Hyster para troca em caso de pane — decisão de qual equipamento substituir provavelmente sem visibilidade digital de disponibilidade.',
                'oravel_solution' => 'Visibilidade de disponibilidade da frota reserva em tempo real, agiliza a troca em caso de pane.',
            ],
            [
                'company_name' => 'Nordeste Locações',
                'website' => 'nordesteloc.com.br',
                'phone' => '8530868667',
                'city' => 'Fortaleza',
                'uf' => 'CE',
                'critical_pain' => '4 unidades entre CE e MA com mix amplo de categorias (empilhadeira, gerador, plataforma) — sem sistema único, controle de manutenção provavelmente fragmentado por categoria e unidade.',
                'oravel_solution' => 'Controle de manutenção único para todas as categorias de equipamento, independente da unidade de origem.',
            ],
            [
                'company_name' => 'Rei das Plataformas',
                'website' => 'reidasplataformas.com.br',
                'phone' => '5133220800',
                'uf' => 'RS',
                'critical_pain' => 'Origem em alpinismo industrial — gestão de manutenção da frota de plataformas provavelmente ainda artesanal, sem sistema formal.',
                'oravel_solution' => 'Sistema simples de implantar, formaliza o controle de manutenção sem exigir estrutura complexa.',
            ],
            [
                'company_name' => 'GMAIS Geradores',
                'website' => 'gmaisgeradores.com.br',
                'phone' => '62996677591',
                'uf' => 'GO',
                'critical_pain' => 'Oficinas móveis com suporte 24h em escala nacional — rastreio de ordem de serviço em campo é o ponto crítico da operação.',
                'oravel_solution' => 'Ordem de serviço mobile rastreável mesmo pra equipe de oficina móvel em campo.',
            ],
            [
                'company_name' => 'TMAX Equipamentos',
                'website' => 'tmaxcompressores.com',
                'phone' => '3134263966',
                'city' => 'Belo Horizonte',
                'uf' => 'MG',
                'critical_pain' => 'No mercado desde 1992, modelo combina venda+locação+manutenção — controle de contrato por cliente/equipamento é candidato a caos manual depois de tantos anos de operação.',
                'oravel_solution' => 'Contrato vinculado ao histórico de manutenção por cliente e equipamento, substitui controle manual acumulado ao longo dos anos.',
            ],
            [
                'company_name' => 'Rede Norte Guindastes',
                'website' => 'redenorteguindastes.com',
                'phone' => '91983552662',
                'email' => 'comercial@redenorteguindastes.com',
                'city' => 'Belém',
                'uf' => 'PA',
                'critical_pain' => 'Atendimento 24h emergencial de alta criticidade na região Norte — despacho sem visibilidade real de disponibilidade é risco de atraso em chamado urgente.',
                'oravel_solution' => 'Visibilidade de disponibilidade de frota antes de despachar equipe, essencial pra atendimento emergencial 24h.',
            ],
            [
                'company_name' => 'Mão Forte Locações',
                'website' => 'maofortelocacoes.com.br',
                'phone' => '19999309588',
                'email' => 'contato@maofortelocacoes.com.br',
                'city' => 'Itapira',
                'uf' => 'SP',
                'critical_pain' => '30+ anos de mercado — processo de gestão de manutenção provavelmente legado, acumulado ao longo de décadas sem digitalização.',
                'oravel_solution' => 'Migração de processo legado para sistema digital, sem perder o histórico acumulado de décadas de operação.',
            ],
            [
                'company_name' => 'Empilhacar',
                'website' => 'empilhacar.com.br',
                'phone' => '4132866239',
                'city' => 'Curitiba',
                'uf' => 'PR',
                'critical_pain' => '29 anos de mercado — perfil clássico de empresa tradicional ainda em planilha/papel para histórico de manutenção.',
                'oravel_solution' => 'Histórico de manutenção digital, substitui controle em planilha acumulado ao longo de quase 3 décadas.',
            ],
            [
                'company_name' => 'Rio Power',
                'website' => 'riopower.com.br',
                'phone' => '2141018601',
                'email' => 'contato@riopower.com.br',
                'city' => 'Rio de Janeiro',
                'uf' => 'RJ',
                'critical_pain' => 'Plantão 24h/7 dias exige altíssima disponibilidade de frota — sem visibilidade em tempo real, risco real de despachar gerador já comprometido.',
                'oravel_solution' => 'Disponibilidade de frota em tempo real, sustenta o plantão 24h/7 dias sem risco de despacho errado.',
            ],
            [
                'company_name' => 'Plus Geradores',
                'website' => 'plusgeradores.com.br',
                'phone' => '21995961022',
                'email' => 'comercial@plusgeradores.com',
                'decision_makers' => [['name' => 'José Antônio de Castro', 'role' => 'Engenheiro Responsável Técnico']],
                'city' => 'Rio de Janeiro',
                'uf' => 'RJ',
                'critical_pain' => 'Operação já formalizada com responsável técnico nomeado — maturidade suficiente pra adotar sistema estruturado de manutenção.',
                'oravel_solution' => 'Sistema estruturado de manutenção que complementa a maturidade técnica já existente na operação.',
            ],
            [
                'company_name' => 'APF Compressores',
                'website' => 'apfcompressores.com.br',
                'phone' => '92992016952',
                'email' => 'contato@apfcompressores.com.br',
                'city' => 'Manaus',
                'uf' => 'AM',
                'critical_pain' => 'Duas unidades em regiões opostas do país (Manaus e Campinas) — coordenação de manutenção entre pontas tão distantes é candidata natural a sistema centralizado.',
                'oravel_solution' => 'Coordenação de manutenção centralizada entre unidades geograficamente opostas.',
            ],
            [
                'company_name' => 'Solumaq Empilhadeiras',
                'website' => 'solumaqempilhadeiras.com.br',
                'phone' => '4132865919',
                'city' => 'Curitiba',
                'uf' => 'PR',
                'critical_pain' => 'Atua em 3 estados desde o início da operação — risco real de crescer geograficamente sem controle formal de manutenção acompanhando.',
                'oravel_solution' => 'Controle de manutenção que acompanha o crescimento geográfico da operação, sem depender de processo manual.',
            ],
            [
                'company_name' => 'JR Guindastes',
                'website' => 'jrguindastes.com.br',
                'phone' => '47999323112',
                'email' => 'contato@jrguindastes.com.br',
                'city' => 'Vale do Itajaí',
                'uf' => 'SC',
                'critical_pain' => 'Operação 24h de alta demanda emergencial — despacho de equipe sem visibilidade de disponibilidade real de frota é risco recorrente.',
                'oravel_solution' => 'Visibilidade de disponibilidade em tempo real, reduz risco de despacho errado em operação 24h de alta demanda.',
            ],
            [
                'company_name' => 'Otto Plataformas Elevatórias',
                'website' => 'ottoplataformaselevatorias.com.br',
                'phone' => '1135994520',
                'email' => 'comercial@ottoplataformas.com.br',
                'city' => 'Osasco',
                'uf' => 'SP',
                'critical_pain' => 'Multi-filial em 3 estados combinando venda+locação+manutenção — visão unificada de frota alugada entre unidades provavelmente fragmentada.',
                'oravel_solution' => 'Visão unificada de frota alugada entre todas as unidades e linhas de negócio (venda, locação, manutenção).',
            ],
            [
                'company_name' => 'Elétrica Minas Bahia',
                'website' => 'eletricaminasbahia.com.br',
                'phone' => '7736280063',
                'decision_makers' => [['name' => 'Hélio Brasil', 'role' => 'Diretor Executivo']],
                'city' => 'Luís Eduardo Magalhães',
                'uf' => 'BA',
                'critical_pain' => 'Maior assistente técnico WEG/EBARA da região com mais de 5 mil peças em estoque — controle de peças e ordem de serviço nesse volume é candidato a sistema formal.',
                'oravel_solution' => 'Controle de peças vinculado à ordem de serviço, essencial pra gerir estoque técnico desse volume.',
            ],
            [
                'company_name' => 'Locapex',
                'website' => 'locapex.com.br',
                'phone' => '5430223300',
                'email' => 'locapex@locapex.com.br',
                'city' => 'Caxias do Sul',
                'uf' => 'RS',
                'critical_pain' => 'Atende 5 estados sem filiais físicas — rastreio de manutenção e disponibilidade à distância é o maior risco operacional.',
                'oravel_solution' => 'Rastreio de manutenção e disponibilidade acessível remotamente, sem depender de presença física em cada estado atendido.',
            ],
            [
                'company_name' => 'Elevar Aluguel de Empilhadeiras',
                'website' => 'elevarempilhadeiras.com.br',
                'phone' => '54999302435',
                'email' => 'elevar@elevarempilhadeiras.com.br',
                'city' => 'Caxias do Sul',
                'uf' => 'RS',
                'critical_pain' => '15+ anos de mercado — momento natural de migração de planilha para sistema conforme a operação amadurece.',
                'oravel_solution' => 'Migração simples de planilha para sistema, sem perder o histórico já acumulado.',
            ],
            [
                'company_name' => 'Requipel',
                'website' => 'empilhadeiras.requipel.com.br',
                'phone' => '5591185655',
                'city' => 'Gravataí',
                'uf' => 'RS',
                'critical_pain' => '40+ anos de mercado com 4 bases técnicas regionais no Sul — padronização de manutenção entre bases é desafio típico de operação madura e capilarizada.',
                'oravel_solution' => 'Padronização de plano de manutenção entre todas as bases técnicas regionais.',
            ],
            [
                'company_name' => 'HERTZ Equipamentos',
                'website' => 'hertzequipamentos.com.br',
                'phone' => '3130174070',
                'city' => 'Belo Horizonte',
                'uf' => 'MG',
                'critical_pain' => 'Mix de locação+venda+peças dificulta visão unificada de manutenção por equipamento — cada linha de negócio provavelmente com controle próprio.',
                'oravel_solution' => 'Visão unificada de manutenção por equipamento, independente da linha de negócio de origem (locação, venda, peças).',
            ],
            [
                'company_name' => 'RXR Locações',
                'website' => 'rxrlocacoes.com.br',
                'phone' => '3137765200',
                'email' => 'contato@rxrlocacoes.com.br',
                'city' => 'Sete Lagoas',
                'uf' => 'MG',
                'critical_pain' => 'Dispersão geográfica (Sete Lagoas, Montes Claros, Uberlândia) combinada com diversidade de categorias de ativo — perfil de dor máxima de coordenação de manutenção.',
                'oravel_solution' => 'Coordenação de manutenção centralizada apesar da dispersão geográfica e diversidade de categorias de ativo.',
            ],
            [
                'company_name' => 'Santos Equipamentos',
                'website' => 'santosequipamentos.com.br',
                'phone' => '31996377975',
                'city' => 'Belo Horizonte',
                'uf' => 'MG',
                'critical_pain' => 'Frota multimarca de plataformas (JLG, Genie, Haulotte) — coordenação de manutenção preventiva entre marcas diferentes provavelmente manual.',
                'oravel_solution' => 'Plano de manutenção preventiva padronizado por ativo, independente da marca do equipamento.',
            ],
            [
                'company_name' => 'DF Geradores',
                'website' => 'dfgeradores.com.br',
                'city' => 'Brasília',
                'uf' => 'DF',
                'critical_pain' => '25 anos de mercado com atendimento 24h para emergências — mesmo padrão de risco de despacho sem visibilidade de disponibilidade dos demais geradores emergenciais.',
                'oravel_solution' => 'Visibilidade de disponibilidade em tempo real antes de despachar equipe pra atendimento emergencial.',
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
