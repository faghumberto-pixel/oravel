<?php

/**
 * Página oravel.com.br/intelligence (teste grátis). FONTE ÚNICA do que a página diz:
 *  - 'exemplo': a locadora fictícia. Todo número, gráfico e tabela da página sai daqui; as variações (+16, +13 pontos, -2,7...) são
 *    calculadas em App\Support\IntelligenceSite\Exemplo, nunca digitadas.
 *  - 'textos': frases editáveis. Preço e prazo NÃO ficam aqui: vêm de config/intelligence_trial.php (plano.preco, dias).
 *
 * A página é HTML estático (o site roda no HostGator): depois de editar este arquivo, rode `php artisan site:intelligence`
 * e publique public_html/intelligence/ (index.html, modelo-planilha-intelligence.xlsx, og-intelligence.png).
 */
return [
    // Para onde o formulário do modal envia e de onde vêm o t.js e o endpoint de depoimentos.
    'app_url' => env('INTELLIGENCE_SITE_APP_URL', 'https://app.oravel.com.br'),
    'site_url' => env('INTELLIGENCE_SITE_URL', 'https://oravel.com.br'),

    // Origens que podem enviar o cadastro rápido (cabeçalho Origin/Referer). O próprio app sempre pode.
    'origens_permitidas' => ['https://oravel.com.br', 'https://www.oravel.com.br'],

    'rotulo_exemplo' => 'Exemplo ilustrativo, com dados fictícios',

    'exemplo' => [
        'frota' => 122,
        // série mensal do índice de saúde (0 a 100)
        'indice' => ['ago' => 61, 'set' => 69, 'out' => 77],
        // % da frota pronta para locar
        'disponibilidade' => ['ago' => 78, 'out' => 91],
        // % do faturamento em atraso. 'meta' é a do próximo ciclo
        'atraso_pct' => ['ago' => 6.6, 'out' => 3.9, 'meta' => 3.0],
        // nota da oficina (0 a 100)
        'oficina' => ['ago' => 41, 'out' => 48],
        // campos que alimentam os valores em reais dos exemplos
        'diaria_media' => 380,
        'dias_parados' => 18,
        'equipamentos_acima_60' => 10,
        'equipamentos_exemplo' => [
            ['nome' => 'Plataforma tesoura 12 m', 'patrimonio' => 'PL-014', 'valor' => 180000, 'manutencao' => 118800],
            ['nome' => 'Empilhadeira 2,5 t', 'patrimonio' => 'EM-007', 'valor' => 95000, 'manutencao' => 64600],
            ['nome' => 'Gerador 80 kVA', 'patrimonio' => 'GE-003', 'valor' => 70000, 'manutencao' => 31500],
        ],
        'pergunta_assistente' => 'Minha disponibilidade aumentou?',
    ],

    'textos' => [
        'marca' => 'Oravel Intelligence',
        'hero_titulo' => 'O centro de inteligência da sua locadora.',
        'hero_subtitulo' => 'Reúne os números do seu negócio e mostra onde a locadora perde dinheiro, o que está caro e o que fazer primeiro. Com sistema, sem sistema ou com o ERP da Oravel.',
        'cta' => 'Começar teste grátis',
        'form_titulo' => 'Crie sua conta e comece o teste',
        'microtexto' => 'Leva 1 minuto. Sem senha e sem cartão. Você confirma pelo e-mail.',
        'baixar_modelo' => 'Baixar modelo da planilha',
        'hero_pontos' => [
            ['t' => 'Funciona com qualquer sistema', 'd' => 'Ou sem sistema nenhum'],
            ['t' => 'Leitura em minutos', 'd' => 'Sem projeto e sem TI'],
            ['t' => 'Pergunte em português', 'd' => 'O assistente responde com os seus números'],
        ],

        'oque_titulo' => 'O que é o Oravel Intelligence',
        'oque_lead' => 'Um centro de inteligência para quem aluga equipamentos. Você traz os números, ele organiza, compara mês a mês e aponta o que fazer.',
        'fluxo' => [
            ['t' => 'Seus números', 'd' => 'De qualquer sistema, de uma planilha feita à mão ou direto do ERP da Oravel.'],
            ['t' => 'O Intelligence lê', 'd' => 'Nota por área, alertas, comparação com o mês anterior e um assistente que responde em português.'],
            ['t' => 'Você decide', 'd' => 'O que vender, trocar ou reformar, onde a oficina trava e quanto do faturamento está em atraso.'],
        ],

        'descobre_titulo' => 'O que você descobre',
        'descobre_lead' => 'As perguntas que mais pesam no caixa de uma locadora, respondidas com os seus números.',

        'calc_titulo' => 'Quanto custa deixar equipamento parado?',

        'leitura_titulo' => 'O que você recebe',
        'leitura_lead' => 'Cinco áreas da empresa, cada uma com a sua nota, e uma leitura que você abre quando quiser.',
        'areas' => [
            ['t' => 'Frota', 'd' => 'Disponibilidade, utilização e quais equipamentos já não valem o que custam.'],
            ['t' => 'Finanças', 'd' => 'Margem, retorno sobre a frota e quanto do faturamento está em atraso.'],
            ['t' => 'Oficina', 'd' => 'Preventiva contra corretiva, tempo de reparo e defeitos que se repetem.'],
            ['t' => 'Comercial', 'd' => 'Propostas que viram contrato e dependência de um único cliente.'],
            ['t' => 'Operação', 'd' => 'Entregas no prazo e produtividade da equipe.'],
        ],
        'em_breve' => 'Em breve no Intelligence: Suprimentos, Logística e Comercial completo.',

        'perfis_titulo' => 'Funciona do jeito que sua locadora já trabalha',
        'perfis' => [
            ['t' => 'Não tenho sistema', 'd' => 'Preencha o modelo de planilha com os números do mês. É o suficiente para começar.'],
            ['t' => 'Tenho sistema e quero algo mais rápido', 'd' => 'Exporte o que o seu sistema já gera e envie. Sem integração, sem projeto, sem esperar a TI.'],
            ['t' => 'Uso o ERP da Oravel', 'd' => 'Exporte a planilha pronta com 1 clique e a leitura sai na hora.'],
        ],

        'como_titulo' => 'Como funciona',
        'como_passos' => [
            ['titulo' => 'Crie sua conta', 'texto' => 'Nome, empresa, WhatsApp e e-mail. Você confirma pelo link que chega no e-mail e já entra, sem senha.'],
            ['titulo' => 'Traga seus números', 'texto' => 'Baixe o modelo e preencha, ou exporte do seu sistema. Uma linha com os números do mês e, se quiser, uma linha por equipamento.'],
            ['titulo' => 'Leia, decida e pergunte', 'texto' => 'A leitura sai em minutos. Atualizou os dados? Reenvie quando quiser: cada envio fica no histórico e você compara com os anteriores.'],
        ],
        'como_nota_erp' => 'Já usa o ERP da Oravel? Exporte a planilha com 1 clique.',

        'preco_titulo' => 'Preço e confiança',
        'confianca' => [
            ['t' => 'Nuvem no Brasil', 'd' => 'Infraestrutura do Google Cloud, em São Paulo.'],
            ['t' => 'Conexão protegida', 'd' => 'HTTPS em todo o acesso e proteção da Cloudflare.'],
            ['t' => 'Backup todo dia', 'd' => 'Os dados são copiados diariamente.'],
            ['t' => 'Dados só seus', 'd' => 'Usados apenas para gerar a sua leitura.'],
        ],

        'faq_titulo' => 'Perguntas frequentes',
        'faq' => [
            ['p' => 'Preciso trocar de sistema?', 'r' => 'Não. Você envia uma planilha, venham os números de onde vierem, ou nem tenha sistema. Quem usa o ERP da Oravel exporta a planilha com 1 clique.'],
            ['p' => 'Quanto custa?', 'r' => '__PRECO__'],
            ['p' => 'E se eu não tiver todos os dados?', 'r' => 'Comece com o que tiver. Só a data de referência é obrigatória, e é preciso preencher ao menos um bloco completo (por exemplo, total de equipamentos e equipamentos locados). O que você não informar fica fora da leitura, e o sistema diz o que faltou.'],
            ['p' => 'Meus dados ficam seguros?', 'r' => 'Sim. O Intelligence roda em infraestrutura do Google Cloud, na região de São Paulo, com conexão criptografada (HTTPS) e proteção da Cloudflare. Os discos do Google Cloud são criptografados por padrão. Os dados são copiados todo dia: backup diário do banco, com um arquivo por cliente guardado por 30 dias, e snapshot diário do disco no Google Cloud. Cada envio fica no histórico e nada é sobrescrito. O assistente de IA só consulta os seus números e nunca altera nada.'],
            ['p' => 'Posso reenviar a planilha depois?', 'r' => 'Pode, quando quiser. Cada envio fica no histórico e nada é sobrescrito. Se o arquivo for idêntico a um que você já enviou, o sistema avisa e não grava de novo.'],
        ],

        'final_titulo' => 'Comece pelo teste e veja a sua locadora com outros olhos.',
        'final_texto' => 'Crie a conta em 1 minuto e receba a primeira leitura.',

        'titulo_pagina' => 'Oravel Intelligence: o centro de inteligência da sua locadora',
        'descricao_pagina' => 'Reúne os números da sua locadora e mostra equipamentos parados, manutenção cara e o que vender, trocar ou reformar. Com ou sem sistema. Teste grátis.',
    ],
];
