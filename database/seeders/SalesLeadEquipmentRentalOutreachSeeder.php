<?php

namespace Database\Seeders;

use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 12 prospects de locação de equipamentos (guindaste, empilhadeira,
 * gerador, compressor, plataforma) levantados via LinkedIn pelo usuário,
 * agosto/2026 -- dor mapeada + rascunho de e-mail de prospecção (D3 da
 * cadência) pronto pra copiar/enviar. Mesmo padrão idempotente de
 * SalesLeadIndustrialProspectsSeeder: update se já existe (por
 * company_name), create se não.
 */
class SalesLeadEquipmentRentalOutreachSeeder extends Seeder
{
    public function run(): void
    {
        $assignedUserId = User::where('email', 'humberto@oravel.com.br')->value('id');

        $prospects = [
            [
                'company_name' => 'Macromaq',
                'email' => 'mauricio.gomes@macromaq.com.br',
                'decision_makers' => [['name' => 'Maurício Gomes', 'role' => 'Gestor de Frota/Manutenção']],
                'critical_pain' => 'Falta de visibilidade de custo por equipamento/contrato para decisão de manutenção e planejamento.',
                'oravel_solution' => 'Controle de custo por ativo em tempo real, cruzando manutenção, contrato e uso.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Custo por equipamento na Macromaq — vale uma troca rápida?

                    Maurício, tudo bem?

                    Sou Humberto, e antes de qualquer coisa: não é uma mensagem de vendedor. Passei mais de 20 anos administrando locadora na prática, então sei o peso de juntar frota, manutenção, contratos e custo sob a mesma cadeira — é raro esse dado de custo por equipamento chegar rápido o suficiente pra virar decisão de planejamento em vez de "achismo".

                    Não sei como está esse processo hoje na Macromaq, mas se fizer sentido, gostaria de trocar uma ideia rápida — 15 minutos, sem powerpoint, só gestor pra gestor sobre como cada um resolve isso na prática.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Eletrac',
                'email' => 'eneas.basso@gmail.com',
                'phone' => '11998719226',
                'decision_makers' => [['name' => 'Enéas Basso Jr.', 'role' => 'Sócio/Gestor']],
                'critical_pain' => 'Avaria no retorno da locação sem prova documental de entrega vs. devolução.',
                'oravel_solution' => 'Checklist digital com foto/assinatura na entrega e devolução, prova de estado do equipamento.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Avaria no retorno da locação — como a Eletrac resolve isso hoje?

                    Enéas, tudo bem?

                    Sou Humberto. Locação de empilhadeira e plataforma tem um problema clássico que pouca empresa resolve bem: provar em que estado o equipamento saiu e voltou. Isso sozinho decide quem paga o conserto quando o cliente devolve avariado — e já vivi isso na pele administrando locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem discurso comercial — sobre como vocês lidam com isso na Eletrac hoje.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'DWT Geradores',
                'email' => 'diogo@dwtgeradores.com.br',
                'phone' => '(11) 4215-2562',
                'address' => 'Rua Iraci Santana, 86 - Centro',
                'city' => 'Guarulhos',
                'uf' => 'SP',
                'cep' => '07112-040',
                'decision_makers' => [['name' => 'Diogo Calor', 'role' => 'Diretor Geral']],
                'critical_pain' => 'Disponibilidade de frota crítica (gerador) e tempo de resposta em mobilização.',
                'oravel_solution' => 'Gestão de mobilização/desmobilização com visibilidade de disponibilidade em tempo real.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Disponibilidade de frota de geradores na DWT

                    Diogo, tudo bem?

                    Sou Humberto. Gerador é um dos poucos equipamentos onde o cliente liga desesperado se ele não estiver disponível na hora — e eu sei o quanto isso pressiona a logística de mobilização de uma locadora, depois de mais de 20 anos vivendo essa operação na prática.

                    Queria entender como a DWT organiza essa ponta hoje — 15 minutos, sem powerpoint, só troca entre quem administra locadora.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Loxam (Adriano Pereira)',
                'email' => 'eng.adriano.pereira@gmail.com',
                'phone' => '(48) 9600-1881',
                'decision_makers' => [['name' => 'Adriano Pereira', 'role' => 'Diretor']],
                'critical_pain' => 'Gestão fragmentada entre múltiplas operações/unidades, sem visão consolidada de manutenção e peças.',
                'oravel_solution' => 'Visão consolidada de frota multi-unidade, priorização de manutenção por dado, não por intuição.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Gestão de frota multi-operação — vale uma troca?

                    Adriano, tudo bem?

                    Sou Humberto. Administrar operação de locação com a escala e a diversidade de frota que vocês têm é osso — a dor raramente é falta de informação, e sim informação espalhada demais entre unidades pra decidir rápido onde priorizar manutenção e reposição de peças. Já vivi isso administrando locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem discurso comercial — sobre como isso funciona aí hoje.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Grupo Maqjato',
                'email' => 'engenharia.ind@maqjato.com.br',
                'decision_makers' => [['name' => 'Jair Camilo', 'role' => 'Diretor Técnico/Gestor de Manutenção']],
                'critical_pain' => 'Tempo consumido em manutenção corretiva reativa, sem espaço para planejamento preventivo.',
                'oravel_solution' => 'Planejamento de preventiva automatizado, reduz tempo apagando incêndio operacional.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Apagar incêndio vs. preventiva — Grupo Maqjato

                    Jair, tudo bem?

                    Sou Humberto. Quando o mesmo gestor cuida da parte técnica e da manutenção, sobra pouco tempo pra sair do apagar incêndio e planejar preventiva de verdade — é uma realidade que conheço bem de quem já viveu isso no chão de locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem powerpoint — sobre como isso funciona no Grupo Maqjato hoje.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Empicamp',
                'email' => 'empicamp@empicamp.com.br',
                'phone' => '(19) 9209-9080',
                'address' => 'R. Dario Freire Meirelles, 175, Campo dos Amarais',
                'city' => 'Campinas',
                'uf' => 'SP',
                'cep' => '13082-045',
                'decision_makers' => [['name' => 'Jean Robson Baptista', 'role' => 'Proprietário']],
                'critical_pain' => 'Falta de tempo/estrutura para profissionalizar processo de manutenção sem virar carga extra.',
                'oravel_solution' => 'Sistema simples de implantar, sem sobrecarregar o dono no dia a dia.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Organizar sem virar mais trabalho — Empicamp

                    Jean, tudo bem?

                    Sou Humberto. Sei bem como é: quando você é dono do negócio, o tempo pra parar e organizar processo de manutenção e avaria sempre perde pro operacional do dia a dia. Já vivi isso administrando locadora por mais de 20 anos — não é sobre implantar algo complicado, é sobre destravar isso sem virar mais trabalho pra você.

                    Topa uma troca rápida — 15 minutos, sem discurso comercial — sobre como isso está hoje na Empicamp?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Enerdgeradores',
                'email' => 'brunotmor@hotmail.com',
                'website' => 'energgeradores.com.br',
                'decision_makers' => [['name' => 'Bruno Moreira', 'role' => 'Contato Comercial']],
                'critical_pain' => 'Disponibilidade de frota de geradores.',
                'oravel_solution' => 'Visibilidade de disponibilidade/manutenção em tempo real.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Disponibilidade de frota de geradores — Enerdgeradores

                    Bruno, tudo bem?

                    Sou Humberto. Gerador é um dos poucos equipamentos onde o cliente liga desesperado se não estiver disponível na hora — sei o quanto isso pressiona quem cuida da operação numa locadora desse porte, depois de mais de 20 anos vivendo isso na prática.

                    Queria entender como a Enerdgeradores organiza essa ponta hoje — 15 minutos, sem powerpoint.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Tecnogera',
                'email' => null,
                'city' => 'São Bernardo do Campo',
                'uf' => 'SP',
                'decision_makers' => [['name' => 'Fernando Anaya', 'role' => 'Gerente de Manutenção Nacional PEMT']],
                'critical_pain' => 'Falta de padronização/visibilidade de manutenção entre unidades (gestão nacional).',
                'oravel_solution' => 'Padronização de processo de manutenção com visão consolidada entre filiais.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Padronização de manutenção entre unidades — Tecnogera

                    Fernando, tudo bem?

                    Sou Humberto. Gerenciar manutenção de PEMT em escala nacional tem um desafio que pouca gente resolve bem: manter padrão entre unidades sem perder visibilidade de qual filial (ou qual equipamento) está consumindo mais manutenção do que deveria. Já vivi essa realidade administrando locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem powerpoint — sobre como isso funciona hoje na Tecnogera.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Cunzolo Guindastes e Plataformas',
                'email' => null,
                'city' => 'Campinas',
                'uf' => 'SP',
                'decision_makers' => [['name' => 'Fabio Cunzolo', 'role' => 'Diretor']],
                'critical_pain' => 'Rastreabilidade de manutenção e segurança operacional em guindaste/plataforma.',
                'oravel_solution' => 'Rastreabilidade documentada de manutenção e condição de entrega.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Rastreabilidade de manutenção em guindaste e plataforma

                    Fabio, tudo bem?

                    Sou Humberto. Guindaste e plataforma são os equipamentos onde falha de manutenção pesa mais que em qualquer outro tipo de frota — não é só custo, é a segurança de quem opera. Rastrear e provar a condição do equipamento na entrega costuma ser um dos pontos mais sensíveis, e já vivi essa realidade administrando locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem powerpoint — sobre como isso funciona hoje na Cunzolo.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Movmix Rental',
                'email' => 'lincolnvg@gmail.com',
                'phone' => '(41) 3046-0100',
                'address' => 'R. Bartolomeu Lourenço de Gusmão, 3699, Boqueirão',
                'city' => 'Curitiba',
                'uf' => 'PR',
                'decision_makers' => [['name' => 'Lincoln Gonçalves', 'role' => 'Diretor']],
                'critical_pain' => 'Falta de dado confiável sobre qual equipamento gera retorno vs. consome manutenção sem retorno.',
                'oravel_solution' => 'Análise de rentabilidade por ativo, decisão de investimento baseada em dado.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Qual equipamento da frota realmente dá retorno — Movmix

                    Lincoln, tudo bem?

                    Sou Humberto. Dirigir uma locadora costuma esbarrar sempre no mesmo ponto: saber com precisão qual equipamento está gerando resultado e qual está só consumindo manutenção sem retorno. Sem esse dado, decisão de investimento vira mais intuição do que fato — e já vivi isso administrando locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem discurso comercial — sobre como isso funciona hoje na Movmix.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Air Service Compressores',
                'email' => 'ralfea@yahoo.com.br',
                'city' => 'Campinas',
                'uf' => 'SP',
                'decision_makers' => [['name' => 'Ralfe Albertini', 'role' => 'CEO']],
                'critical_pain' => 'Pouco tempo para formalizar processo de manutenção (CEO técnico e operacional ao mesmo tempo).',
                'oravel_solution' => 'Sistema que formaliza processo sem exigir tempo de implantação complexo.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: 15 anos de Air Service — vale uma troca rápida?

                    Ralfe, tudo bem?

                    Sou Humberto. 15 anos de Air Service dizem muito sobre o cuidado técnico que vocês têm — e, pela experiência de mais de 20 anos que tive administrando locadora, sei que é justamente esse nível de exigência técnica que deixa pouco tempo sobrando pra formalizar processo de manutenção e controle de frota.

                    Queria trocar uma ideia rápida — 15 minutos, sem powerpoint — sobre como isso está organizado hoje na Air Service.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
            ],
            [
                'company_name' => 'Tecmax Geradores',
                'email' => 'taliyoyo16@gmail.com',
                'city' => 'Campinas',
                'uf' => 'SP',
                'decision_makers' => [['name' => 'Talita Vieira', 'role' => 'CEO']],
                'critical_pain' => 'Decisão de investimento em frota sem dado confiável de disponibilidade/uso.',
                'oravel_solution' => 'Dado de disponibilidade e uso por equipamento para decisão de investimento.',
                'outreach_email_draft' => <<<'EOT'
                    Assunto: Qual gerador da frota realmente vale manter — Tecmax

                    Talita, tudo bem?

                    Sou Humberto. Gerador é ativo que o cliente aluga com urgência, e pra quem está à frente do negócio a pergunta real costuma ser: qual equipamento da frota realmente vale a pena manter ou repor, com dado de verdade, não só intuição. Já vivi essa decisão na prática administrando locadora por mais de 20 anos.

                    Queria trocar uma ideia rápida — 15 minutos, sem discurso comercial — sobre como isso funciona hoje na Tecmax.

                    Topa um café virtual essa semana?

                    Abraço,
                    Humberto
                    EOT,
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
                'source' => SalesLead::SOURCE_PROSPECCAO_ATIVA,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'assigned_user_id' => $assignedUserId,
            ]));
        }
    }
}
