<?php

use App\Filament\Pages\CrmFunil;
use App\Filament\Pages\ImportarPlanilhaCrm;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmPropostaResource;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\MaintenancePlanResource;
use App\Filament\Resources\PropostaComercialResource;
use App\Support\Onboarding\Detectores\AtivosCadastrados;
use App\Support\Onboarding\Detectores\ClientesImportados;
use App\Support\Onboarding\Detectores\InteracaoRegistrada;
use App\Support\Onboarding\Detectores\LeadsCadastrados;
use App\Support\Onboarding\Detectores\PrimeiraOrdemDeServico;
use App\Support\Onboarding\Detectores\PrimeiroPlanoPreventivo;
use App\Support\Onboarding\Detectores\PropostaCrmEnviada;
use App\Support\Onboarding\Detectores\PropostaEnviada;
use App\Support\Onboarding\Detectores\PropostaVista;

/**
 * Onboarding guiado: por solução (código de pacote em config/pacotes.php), os passos que levam o cliente ao primeiro resultado
 * no mesmo dia. Para ligar uma solução nova basta acrescentar a entrada em `solucoes`; a tela, o progresso, os lembretes e as
 * métricas leem só daqui.
 *
 * Passo: chave, titulo, descricao, minutos, detector (chave de `detectores`) e acoes[]. Cada ação tem `rotulo` e UM destes:
 *  - `recurso` + `pagina` (resource do painel admin e a página dele, ex.: index/create) → vai direto à tela;
 *  - `pagina_filament` (classe de uma Page do painel, ex.: a importação de planilha do CRM) → vai direto à tela;
 *  - `acao` (baixar_modelo | importar), tratada pela própria tela de Primeiros passos;
 *  - `aula` (slug do curso da Academia) → abre a aula.
 * Closures não entram aqui (config:cache): o detector é sempre uma classe.
 */
return [
    // Botão de ajuda do rodapé da tela de Primeiros passos.
    'whatsapp' => '5519999332615',

    // Lembretes por e-mail (onboarding:lembretes): só em dias úteis, dentro desta janela, no fuso abaixo.
    'lembretes' => [
        'fuso' => 'America/Sao_Paulo',
        'hora_inicio' => 9,
        'hora_fim' => 18,
        'dias_uteis' => true,
    ],

    // Limite de linhas por planilha de clientes (a fila de produção é síncrona: arquivos maiores devem ser divididos).
    'importacao_max_linhas' => 500,

    'detectores' => [
        'clientes_importados' => ClientesImportados::class,
        'proposta_enviada' => PropostaEnviada::class,
        'proposta_vista' => PropostaVista::class,
        'ativos_cadastrados' => AtivosCadastrados::class,
        'primeira_os' => PrimeiraOrdemDeServico::class,
        'primeiro_plano_preventivo' => PrimeiroPlanoPreventivo::class,
        'leads_cadastrados' => LeadsCadastrados::class,
        'interacao_registrada' => InteracaoRegistrada::class,
        'proposta_crm_enviada' => PropostaCrmEnviada::class,
    ],

    'solucoes' => [
        'servicos' => [
            'titulo' => 'Serviços',
            'meta' => 'enviar sua primeira proposta',
            'conclusao' => 'Sua primeira proposta já chegou ao cliente.',
            'passos' => [
                [
                    'chave' => 'importar_clientes',
                    'titulo' => 'Importe seus clientes',
                    'descricao' => 'Baixe a planilha modelo, preencha com seus clientes e envie. O sistema confere e avisa se alguma linha tiver erro.',
                    'minutos' => 10,
                    'detector' => 'clientes_importados',
                    'acoes' => [
                        ['rotulo' => 'Baixar planilha modelo', 'acao' => 'baixar_modelo', 'icone' => 'heroicon-o-arrow-down-tray'],
                        ['rotulo' => 'Enviar planilha preenchida', 'acao' => 'importar', 'icone' => 'heroicon-o-arrow-up-tray'],
                    ],
                ],
                [
                    'chave' => 'primeira_proposta',
                    'titulo' => 'Crie sua primeira proposta',
                    'descricao' => 'Escolha um cliente, adicione os serviços e envie. O cliente recebe um link para aceitar online.',
                    'minutos' => 10,
                    'detector' => 'proposta_enviada',
                    'acoes' => [
                        ['rotulo' => 'Criar proposta', 'recurso' => PropostaComercialResource::class, 'pagina' => 'create', 'icone' => 'heroicon-o-document-text'],
                        ['rotulo' => 'Assistir aula de 5 min', 'aula' => 'comece-por-aqui-servicos', 'icone' => 'heroicon-o-play'],
                    ],
                ],
                [
                    'chave' => 'acompanhar_aceite',
                    'titulo' => 'Acompanhe o aceite',
                    'descricao' => 'Veja quando o cliente abrir e aceitar. A proposta aceita vira contrato de serviço.',
                    'minutos' => 5,
                    'detector' => 'proposta_vista',
                    'acoes' => [
                        ['rotulo' => 'Ver propostas enviadas', 'recurso' => PropostaComercialResource::class, 'pagina' => 'index', 'icone' => 'heroicon-o-chart-bar'],
                    ],
                ],
            ],
        ],

        'manutencao' => [
            'titulo' => 'Manutenção',
            'meta' => 'abrir sua primeira ordem de serviço',
            'conclusao' => 'Seus equipamentos, a primeira ordem de serviço e o primeiro plano preventivo já estão no sistema.',
            'passos' => [
                [
                    'chave' => 'cadastrar_equipamentos',
                    'titulo' => 'Cadastre seus equipamentos',
                    'descricao' => 'Cadastre pelo menos 3 equipamentos, um a um ou importando a planilha (botão Importar Excel na lista). Depois, imprima a etiqueta com QR Code de cada um.',
                    'minutos' => 10,
                    'detector' => 'ativos_cadastrados',
                    'acoes' => [
                        ['rotulo' => 'Cadastrar equipamento', 'recurso' => AssetResource::class, 'pagina' => 'create', 'icone' => 'heroicon-o-plus'],
                        ['rotulo' => 'Importar planilha', 'recurso' => AssetResource::class, 'pagina' => 'index', 'icone' => 'heroicon-o-arrow-up-tray'],
                    ],
                ],
                [
                    'chave' => 'primeira_os',
                    'titulo' => 'Abra sua primeira ordem de serviço',
                    'descricao' => 'Escolha um equipamento, descreva o serviço e abra a OS. Ela aparece no kanban do pátio e o técnico a recebe no celular.',
                    'minutos' => 5,
                    'detector' => 'primeira_os',
                    'acoes' => [
                        ['rotulo' => 'Abrir ordem de serviço', 'recurso' => MaintenanceOrderResource::class, 'pagina' => 'create', 'icone' => 'heroicon-o-wrench-screwdriver'],
                    ],
                ],
                [
                    'chave' => 'primeiro_plano_preventivo',
                    'titulo' => 'Crie seu primeiro plano preventivo',
                    'descricao' => 'Defina de quanto em quanto tempo (horas, dias ou ciclos) um equipamento precisa de manutenção. O sistema passa a avisar quando vencer.',
                    'minutos' => 5,
                    'detector' => 'primeiro_plano_preventivo',
                    'acoes' => [
                        ['rotulo' => 'Criar plano preventivo', 'recurso' => MaintenancePlanResource::class, 'pagina' => 'create', 'icone' => 'heroicon-o-calendar-days'],
                    ],
                ],
            ],
        ],

        'vendas' => [
            'titulo' => 'Vendas',
            'meta' => 'enviar sua primeira proposta',
            'conclusao' => 'Seus leads, o primeiro contato e a primeira proposta já estão no CRM.',
            'passos' => [
                [
                    'chave' => 'cadastrar_leads',
                    'titulo' => 'Cadastre seus leads',
                    'descricao' => 'Importe sua planilha de contatos ou cadastre pelo menos 3 leads. O funil de vendas já vem pronto; ajuste as etapas quando quiser.',
                    'minutos' => 10,
                    'detector' => 'leads_cadastrados',
                    'acoes' => [
                        ['rotulo' => 'Importar planilha', 'pagina_filament' => ImportarPlanilhaCrm::class, 'icone' => 'heroicon-o-arrow-up-tray'],
                        ['rotulo' => 'Cadastrar lead', 'recurso' => CrmLeadResource::class, 'pagina' => 'create', 'icone' => 'heroicon-o-plus'],
                    ],
                ],
                [
                    'chave' => 'registrar_contato',
                    'titulo' => 'Registre seu primeiro contato',
                    'descricao' => 'Abra um lead e registre a ligação, a mensagem ou a visita que você fez. O histórico fica guardado na ficha dele.',
                    'minutos' => 5,
                    'detector' => 'interacao_registrada',
                    'acoes' => [
                        ['rotulo' => 'Ver meus leads', 'recurso' => CrmLeadResource::class, 'pagina' => 'index', 'icone' => 'heroicon-o-users'],
                        ['rotulo' => 'Abrir o funil de vendas', 'pagina_filament' => CrmFunil::class, 'icone' => 'heroicon-o-view-columns'],
                    ],
                ],
                [
                    'chave' => 'enviar_proposta',
                    'titulo' => 'Envie sua primeira proposta',
                    'descricao' => 'Monte a proposta com produtos e serviços para um lead e envie. Você acompanha quando o cliente abrir e responder.',
                    'minutos' => 10,
                    'detector' => 'proposta_crm_enviada',
                    'acoes' => [
                        ['rotulo' => 'Criar proposta', 'recurso' => CrmPropostaResource::class, 'pagina' => 'create', 'icone' => 'heroicon-o-document-text'],
                    ],
                ],
            ],
        ],
    ],
];
