<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 3a leva de locadoras regionais de medio porte (guindaste/munck,
 * empilhadeira, plataforma, gerador, compressor), levantadas via
 * prospeccao ativa em 2026-08 -- validadas com site institucional proprio
 * e contato extraido da fonte primaria, nunca inventado. Cobre estados
 * ainda pouco representados nas 2 levas anteriores: BA, GO, ES, SE, AM, MS,
 * PR, DF. Mesmo padrao idempotente das levas anteriores: update se ja
 * existe (por company_name), create se nao. Sem sobreposicao com as 63
 * empresas ja cadastradas antes (conferido antes de escrever este seeder).
 */
class SalesLeadRegionalRentersRoundThreeSeeder extends Seeder
{
    public function run(): void
    {
        $assignedUserId = User::where('email', 'humberto@oravel.com.br')->value('id');

        $prospects = [
            [
                'company_name' => 'Falcão Guindastes e Locações',
                'website' => 'falcaoguinchos.com.br',
                'phone' => '75992208218',
                'email' => 'contatos@falcaoguinchos.com.br',
                'city' => 'Feira de Santana',
                'uf' => 'BA',
                'critical_pain' => 'Frota heterogênea (guindaste, munck, guincho 24h, empilhadeira, plataforma) com atendimento emergencial contínuo — alta pressão pra manter disponibilidade e rastrear manutenção de múltiplas categorias sem sistema dedicado.',
                'oravel_solution' => 'Controle de manutenção único para todas as categorias de equipamento, sustenta o atendimento emergencial 24h sem perder rastreabilidade.',
            ],
            [
                'company_name' => 'PRISMA Locação de Guindastes',
                'website' => 'prismaguindastes.com',
                'phone' => '62996973838',
                'email' => 'locacao@prismaguindastes.com',
                'city' => 'Goiânia',
                'uf' => 'GO',
                'critical_pain' => 'Operação especializada em rigging/içamento depende de disponibilidade e certificação constante do equipamento — falha de manutenção gera risco direto de segurança e parada de obra.',
                'oravel_solution' => 'Plano de manutenção preventiva rastreável, essencial pra sustentar certificação e segurança em operação de rigging/içamento.',
            ],
            [
                'company_name' => 'Nacional Guindastes',
                'website' => 'nacionalalugueldeguindastes.click',
                'phone' => '62999293783',
                'email' => 'atendimento@guindastegoiania.com.br',
                'city' => 'Aparecida de Goiânia',
                'uf' => 'GO',
                'critical_pain' => 'Frota diversificada (guindastes 15-250t, muncks, plataformas, empilhadeiras) com operação 24h/7 dias — mix de categorias e alta rotatividade de uso é terreno fértil pra falha de rastreamento de OS.',
                'oravel_solution' => 'Ordem de serviço centralizada para todas as categorias de ativo, mesmo em operação 24h de alta rotatividade.',
            ],
            [
                'company_name' => 'LOCVIX Guindastes e Serviços',
                'website' => 'locvix.ind.br',
                'phone' => '27996456723',
                'email' => 'contato@locvix.ind.br',
                'city' => 'Serra',
                'uf' => 'ES',
                'critical_pain' => 'Atendimento emergencial 24h com frota multi-categoria (guindaste, guindauto, retroescavadeira, basculante) — gestão de disponibilidade em cima da hora exige visibilidade de manutenção em tempo real.',
                'oravel_solution' => 'Visibilidade de manutenção e disponibilidade em tempo real, sustenta atendimento emergencial multi-categoria.',
            ],
            [
                'company_name' => 'S&P Guindastes',
                'website' => 'splocacoes.com.br',
                'phone' => '79998123070',
                'email' => 'anapaula@splocacoes.com.br',
                'city' => 'Aracaju',
                'uf' => 'SE',
                'critical_pain' => 'Empresa regional em praça pouco disputada (Sergipe) — provável dependência de controle manual de manutenção e agenda de locação, sem histórico digitalizado por equipamento.',
                'oravel_solution' => 'Histórico digital de manutenção por equipamento, substitui controle manual de agenda de locação.',
            ],
            [
                'company_name' => 'Multylog Empilhadeiras',
                'website' => 'multylog.com.br',
                'phone' => '81994064202',
                'email' => 'comercial@multylog.com.br',
                'city' => 'Recife',
                'uf' => 'PE',
                'critical_pain' => 'Representante exclusivo STILL cobrindo 5 estados (PE, PB, AL, RN, SE) a partir de uma base única — logística de manutenção multi-estado (peças, técnicos, prazos) é o ponto de dor claro para escalar sem perder SLA.',
                'oravel_solution' => 'Coordenação de manutenção multi-estado a partir de uma base única, sustenta SLA mesmo com cobertura ampliada.',
            ],
            [
                'company_name' => 'Versátil Locação de Empilhadeiras',
                'website' => 'versatilsc.com.br',
                'phone' => '48991432588',
                'city' => 'Içara',
                'uf' => 'SC',
                'critical_pain' => 'Locação multimarcas com oficina própria — dependência de agendamento manual de manutenção preventiva/corretiva multimarca é risco de parada não planejada para os clientes que alugam.',
                'oravel_solution' => 'Agendamento de manutenção preventiva estruturado por marca, reduz risco de parada não planejada pro cliente locatário.',
            ],
            [
                'company_name' => 'Nova Empilhadeiras',
                'website' => 'novaempilhadeirasgo.com.br',
                'phone' => '62998319133',
                'email' => 'novaempilhadeiras@hotmail.com',
                'city' => 'Goiânia',
                'uf' => 'GO',
                'critical_pain' => 'Suporte técnico 24h/7 dias com frota própria de empilhadeiras a combustão — a promessa de atendimento contínuo pressiona diretamente a gestão de manutenção preventiva pra evitar quebra em campo.',
                'oravel_solution' => 'Manutenção preventiva rastreável por horímetro, reduz risco de quebra em campo sob promessa de atendimento 24h.',
            ],
            [
                'company_name' => 'Vaine Empilhadeira',
                'website' => 'vaineempilhadeira.com.br',
                'phone' => '61999177941',
                'email' => 'contato@vaineempilhadeira.com.br',
                'city' => 'Brasília',
                'uf' => 'DF',
                'critical_pain' => 'Atende Brasília e cidades satélites/entorno (Formosa, Cristalina, Luziânia, Anápolis) — dispersão geográfica de clientes e ativos alugados dificulta rastrear manutenção por equipamento fora da matriz.',
                'oravel_solution' => 'Rastreio de manutenção por equipamento acessível remotamente, sem depender de presença física na matriz.',
            ],
            [
                'company_name' => 'Locação Empilhadeiras Joinville',
                'website' => 'locacaoempilhadeiras.com.br',
                'phone' => '68999745566',
                'email' => 'contato@locacaoempilhadeiras.com.br',
                'city' => 'Joinville',
                'uf' => 'SC',
                'critical_pain' => 'Promete substituição imediata em caso de falha e suporte 24/7 em 6 cidades de SC — esse compromisso comercial só se sustenta com controle rígido de manutenção preventiva, hoje aparentemente não sistematizado.',
                'oravel_solution' => 'Controle de manutenção preventiva estruturado, sustenta a promessa de substituição imediata em caso de falha.',
            ],
            [
                'company_name' => 'LOCTON RENTAL',
                'website' => 'locton.com.br',
                'phone' => '47991330220',
                'email' => 'contato@locton.com.br',
                'city' => 'Joinville',
                'uf' => 'SC',
                'critical_pain' => 'Frota de plataformas elétricas e diesel 4x4 com manutenção preventiva constante citada como diferencial — já reconhecem o problema, mas provavelmente sem ferramenta dedicada de gestão de manutenção.',
                'oravel_solution' => 'Ferramenta dedicada de gestão de manutenção, formaliza o diferencial que a empresa já promete comercialmente.',
            ],
            [
                'company_name' => 'Jaco Locação',
                'website' => 'jacolocadora.com.br',
                'phone' => '48999250605',
                'city' => 'Palhoça',
                'uf' => 'SC',
                'critical_pain' => 'Portfólio multimarcas (Bobcat, Case, CAT, Genie, JCB, JLG etc.) com entrega rápida para todo o estado — variedade de marcas/modelos torna o controle de manutenção por especificação técnica especialmente complexo em planilha.',
                'oravel_solution' => 'Controle de manutenção por especificação técnica de cada ativo, independente da marca ou modelo.',
            ],
            [
                'company_name' => 'Dunloc Locação de Equipamentos',
                'website' => 'dunloc.com.br',
                'phone' => '92999950345',
                'email' => 'comercial@dunloc.com.br',
                'city' => 'Manaus',
                'uf' => 'AM',
                'critical_pain' => 'Mais de 300 equipamentos, entrega em até 24h e suporte técnico 24h em região logisticamente difícil (Amazonas) — a promessa de resposta rápida em praça isolada torna crítica a antecipação de falhas via manutenção preventiva.',
                'oravel_solution' => 'Antecipação de falhas via manutenção preventiva estruturada, essencial em praça logisticamente isolada.',
            ],
            [
                'company_name' => 'Gera Mais Geradores',
                'website' => 'geramaisgeradores.com.br',
                'phone' => '81992187213',
                'email' => 'comercial@geramaisgeradores.com.br',
                'city' => 'Recife',
                'uf' => 'PE',
                'critical_pain' => 'Atende clientes institucionais (Compesa, prefeituras, Unimed) com monitoramento remoto citado como serviço — já sentem a dor de acompanhar estado de cada gerador em campo, potencialmente sem sistema estruturado de OS.',
                'oravel_solution' => 'Ordem de serviço estruturada vinculada ao monitoramento remoto, formaliza o que hoje é feito de forma manual.',
            ],
            [
                'company_name' => 'Grupo SC Geradores',
                'website' => 'gruposcg.com.br',
                'phone' => '48984684342',
                'email' => 'contato@scgeradores.com.br',
                'city' => 'Palhoça',
                'uf' => 'SC',
                'critical_pain' => 'Instalação, manutenção preventiva/corretiva e fabricação de QTA em 3 estados (SC, RS, PR) a partir de escritórios regionais — coordenar agenda de manutenção multi-regional é o gargalo natural do modelo.',
                'oravel_solution' => 'Agenda de manutenção coordenada entre escritórios regionais, resolve o gargalo de operação multi-estado.',
            ],
            [
                'company_name' => 'R7 Geradores',
                'website' => 'r7geradores.com.br',
                'phone' => '27996627007',
                'city' => 'Vila Velha',
                'uf' => 'ES',
                'critical_pain' => 'Atende multimarcas de motor (Scania, Volvo, Perkins, Cummins, CAT, Yanmar, MTU, MAN) com automatização e laudos ART — a diversidade de fabricantes exige histórico técnico por equipamento que planilha não sustenta bem.',
                'oravel_solution' => 'Histórico técnico por equipamento, independente do fabricante do motor, com rastreabilidade pra laudo ART.',
            ],
            [
                'company_name' => 'Gran Loc Locação e Manutenção',
                'website' => 'granloc.com.br',
                'phone' => '27997202905',
                'email' => 'granloc@granloc.com.br',
                'city' => 'Serra',
                'uf' => 'ES',
                'critical_pain' => '40+ anos de operação, mais de 4.500 clientes atendidos, faixa de 3 a 1.500 kVA — volume histórico grande de ativos e clientes é sinal claro de que o controle de manutenção em planilha/manual já não escala.',
                'oravel_solution' => 'Sistema estruturado de manutenção que escala com o volume de ativos e clientes acumulado em 40+ anos de operação.',
            ],
            [
                'company_name' => 'Grupo Energe',
                'website' => 'grupoenerge.com.br',
                'phone' => '67981532330',
                'city' => 'Campo Grande',
                'uf' => 'MS',
                'critical_pain' => 'Frota própria de 13 veículos (incluindo caminhões Munck) cobrindo 11 cidades do MS com atendimento emergencial prometido em 5 minutos — cobertura extensa em estado de grandes distâncias é terreno propício pra falha de rastreamento por unidade móvel.',
                'oravel_solution' => 'Rastreamento de manutenção por unidade móvel, sustenta cobertura extensa com atendimento emergencial rápido.',
            ],
            [
                'company_name' => 'RJ Compressores',
                'website' => 'rjcompressores.com.br',
                'phone' => '4791820330',
                'email' => 'mkt@rjcompressores.com.br',
                'city' => 'Rio do Sul',
                'uf' => 'SC',
                'critical_pain' => 'Três unidades operacionais cobrindo Vale do Itajaí, Serrana, Meio-Oeste e Sul de SC — modelo de múltiplas unidades regionais sem visão unificada de manutenção entre elas é risco direto de duplicidade/perda de histórico.',
                'oravel_solution' => 'Visão unificada de manutenção entre todas as unidades regionais, elimina duplicidade e perda de histórico.',
            ],
            [
                'company_name' => 'Ar Norte Compressores e Serviços',
                'website' => 'arnortecompressores.com.br',
                'phone' => '4331521053',
                'email' => 'vendas@arnortecompressores.com.br',
                'city' => 'Arapongas',
                'uf' => 'PR',
                'critical_pain' => 'Atendimento multimarcas (Chicago Pneumatic, Atlas Copco, Schulz, Ingersoll Rand, Kaeser) 24h/7 dias — mix de fabricantes com peças e ciclos de manutenção distintos por marca é ponto de fricção operacional clássico.',
                'oravel_solution' => 'Ciclo de manutenção configurável por marca/modelo, resolve a fricção operacional do mix multimarca.',
            ],
            [
                'company_name' => 'AMS Geradores e Serviços',
                'website' => 'amsgeradores.com.br',
                'phone' => '79999625830',
                'email' => 'contato@amsgeradores.com.br',
                'city' => 'Aracaju',
                'uf' => 'SE',
                'critical_pain' => 'Atua com gerador, compressor de ar e plataforma elevatória na mesma operação — mix de categorias distintas sem sistema único de manutenção é risco de controle fragmentado por linha de negócio.',
                'oravel_solution' => 'Controle de manutenção único para todas as categorias de equipamento (gerador, compressor, plataforma), sem fragmentação por linha de negócio.',
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
