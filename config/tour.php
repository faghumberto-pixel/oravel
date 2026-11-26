<?php

use App\Filament\Pages\IntelligenceAssistente;
use App\Filament\Pages\IntelligenceComparacao;
use App\Filament\Pages\IntelligenceDashboard;
use App\Filament\Pages\IntelligenceDiagnostico;
use App\Filament\Pages\IntelligenceEquipamentos;
use App\Filament\Pages\IntelligenceOperacao;
use App\Filament\Pages\IntelligencePlano;
use App\Filament\Resources\IntelligenceImportResource;

/**
 * Passo a passo em pop-up DENTRO de cada tela (App\Livewire\TourGuiado): explica o que a tela representa, por que, quando e como usar.
 * Abre sozinho na primeira visita do usuário a cada tela e pode ser reaberto a qualquer momento pelo botão "Passo a passo" no topo.
 * Não confundir com "Primeiros passos" (config/onboarding.php), que é o checklist de tarefas.
 *
 * Para criar o tour de outro produto: nova entrada em `tours`, com `produto` = código do pacote (config/pacotes.php). Cada passo:
 *  - chave, titulo, resumo (uma frase), porque / quando / como (curtos, linguagem de dono de locadora);
 *  - tela: classe de uma Page ou Resource do painel admin. O pop-up abre nessa tela, e o passo só existe para quem tem acesso a ela
 *    (plano e permissão). Closures não entram aqui (config:cache);
 *  - nota_trial (opcional): frase extra, mostrada só a quem está no teste grátis do Intelligence.
 * A ordem da lista é a ordem do "Próxima tela".
 */
return [
    'tours' => [
        'intelligence' => [
            'produto' => 'intelligence',
            'titulo' => 'Passo a passo do Oravel Intelligence',
            'passos' => [
                [
                    'chave' => 'importacoes',
                    'titulo' => 'Importações: envie a sua planilha',
                    'resumo' => 'É daqui que tudo começa.',
                    'porque' => 'Sem planilha enviada, as outras telas ficam vazias. Cada envio gera uma nova leitura e nada é sobrescrito.',
                    'quando' => 'Na primeira vez, para ver a leitura, e depois sempre que atualizar os dados.',
                    'como' => 'Baixe o "Modelo de importação (Excel)", preencha a linha com os números do mês (e, se quiser, uma linha por equipamento) e use "Importar Excel". Se faltar algo, o sistema avisa em português.',
                    'nota_exemplo' => 'ATENÇÃO: os números que você vê agora são dados de DEMONSTRAÇÃO, só para mostrar como fica a tela com dados. Assim que for começar com os seus, clique em "Limpar dados de exemplo" (no aviso no topo) ou envie sua planilha e confirme a limpeza.',
                    'nota_trial' => 'O teste grátis de 30 dias começou no seu primeiro acesso. O aviso no alto da tela mostra os dias restantes.',
                    'tela' => IntelligenceImportResource::class,
                ],
                [
                    'chave' => 'indice',
                    'titulo' => 'Índice de Saúde: como está a locadora',
                    'resumo' => 'Uma nota de 0 a 100 e a nota de cada área.',
                    'porque' => 'Resume a empresa em um número e mostra onde olhar primeiro.',
                    'quando' => 'Logo depois de cada envio e no começo da semana.',
                    'como' => 'Veja a nota geral e a de cada área. As cores indicam o que pede atenção, e a comparação com o mês anterior mostra o que melhorou ou piorou.',
                    'tela' => IntelligenceDashboard::class,
                ],
                [
                    'chave' => 'diagnostico',
                    'titulo' => 'Diagnóstico: o que atacar primeiro',
                    'resumo' => 'Os pontos de atenção, do mais grave ao menos grave.',
                    'porque' => 'Mostra o que está puxando a nota para baixo e por que isso importa.',
                    'quando' => 'Depois do Índice de Saúde, para escolher por onde começar.',
                    'como' => 'Leia cada ponto de atenção e comece pelos mais críticos. Os pontos fortes também aparecem, para você saber o que manter.',
                    'tela' => IntelligenceDiagnostico::class,
                ],
                [
                    'chave' => 'operacao',
                    'titulo' => 'Frota, finanças e oficina: o detalhe por trás da nota',
                    'resumo' => 'Os números de cada área, com gráficos.',
                    'porque' => 'Quando um ponto do Diagnóstico precisa de mais detalhe, é aqui que você enxerga o que mudou.',
                    'quando' => 'Para investigar a frota, o atraso em finanças ou a rotina da oficina.',
                    'como' => 'Acompanhe os indicadores e os gráficos de cada área e compare com os meses anteriores.',
                    'tela' => IntelligenceOperacao::class,
                ],
                [
                    'chave' => 'equipamentos',
                    'titulo' => 'Equipamentos: o que vender, trocar ou reformar',
                    'resumo' => 'Cada equipamento recebe uma situação, com o motivo.',
                    'porque' => 'Mostra quais equipamentos explicam os números, para a decisão deixar de ser opinião.',
                    'quando' => 'Ao decidir o que vender, trocar, reformar ou oferecer para locação.',
                    'como' => 'Envie também a aba "Equipamentos" da planilha (uma linha por equipamento). Quem usa o ERP da Oravel exporta essa aba com 1 clique. A situação é um sinal para avaliar, a decisão continua sendo sua.',
                    'tela' => IntelligenceEquipamentos::class,
                ],
                [
                    'chave' => 'comparacao',
                    'titulo' => 'Comparação interna: a locadora contra ela mesma',
                    'resumo' => 'Veja se a empresa está melhorando.',
                    'porque' => 'Mostra o que evoluiu e o que ficou para trás entre dois envios.',
                    'quando' => 'Todo mês, depois de enviar a planilha nova.',
                    'como' => 'Escolha duas datas já enviadas. Verde melhorou, vermelho piorou.',
                    'tela' => IntelligenceComparacao::class,
                ],
                [
                    'chave' => 'plano',
                    'titulo' => 'Plano de evolução: o que fazer e em que ordem',
                    'resumo' => 'Ações em 30, 60 e 90 dias, com metas.',
                    'porque' => 'Transforma o diagnóstico em ação, com prioridade e meta para o próximo ciclo.',
                    'quando' => 'Depois do Diagnóstico, para montar a pauta da reunião com a equipe.',
                    'como' => 'Siga as ações na ordem. No mês seguinte, reenvie a planilha e meça o avanço contra o plano.',
                    'tela' => IntelligencePlano::class,
                ],
                [
                    'chave' => 'assistente',
                    'titulo' => 'Assistente de IA: pergunte em português',
                    'resumo' => 'Respostas com os números da sua empresa.',
                    'porque' => 'Tira a dúvida na hora, sem abrir planilha.',
                    'quando' => 'Para uma checagem rápida ou para se preparar para uma reunião.',
                    'como' => 'Escreva uma pergunta, por exemplo "minha disponibilidade aumentou?". O assistente só consulta os resultados e nunca altera nada.',
                    'tela' => IntelligenceAssistente::class,
                ],
            ],
        ],
    ],
];
