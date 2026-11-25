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
        'eyebrow' => 'Para locadoras de equipamentos',
        'hero_titulo' => 'Veja onde sua locadora está perdendo dinheiro.',
        'hero_subtitulo' => 'Envie sua planilha e receba a leitura em minutos. Sem trocar de sistema.',
        'cta' => 'Enviar minha planilha',
        'microtexto' => 'Leva 1 minuto. Sem senha e sem cartão.',
        'baixar_modelo' => 'Baixar modelo da planilha',

        'descobre_titulo' => 'O que você descobre',
        'como_titulo' => 'Como funciona',
        'como_passos' => [
            ['titulo' => 'Baixe o modelo e preencha', 'texto' => 'Uma linha com os números do mês. Se quiser, uma linha por equipamento.'],
            ['titulo' => 'Envie', 'texto' => 'A leitura sai sozinha, em minutos.'],
            ['titulo' => 'Atualizou os dados? Reenvie', 'texto' => 'Quando quiser, sem esperar o fim do mês. Cada envio fica no histórico e você compara com os anteriores.'],
        ],
        'como_nota_erp' => 'Já usa o ERP da Oravel? Exporte a planilha com 1 clique.',

        'leitura_titulo' => 'O que vem na leitura',

        'preco_titulo' => 'Preço e confiança',
        'preco_linha_final' => 'Não compara com outras locadoras e não substitui seu contador.',

        'faq_titulo' => 'Perguntas frequentes',
        'faq' => [
            ['p' => 'Preciso trocar de sistema?', 'r' => 'Não. Você envia uma planilha, venham os números de onde vierem. Quem usa o ERP da Oravel exporta a planilha com 1 clique.'],
            ['p' => 'Quanto custa?', 'r' => '__PRECO__'],
            ['p' => 'E se eu não tiver todos os dados?', 'r' => 'Comece com o que tiver. Só a data de referência é obrigatória, e é preciso preencher ao menos um bloco completo (por exemplo, total de equipamentos e equipamentos locados). O que você não informar fica fora da leitura, e o sistema diz o que faltou.'],
            ['p' => 'Meus dados ficam seguros?', 'r' => 'Só a sua empresa vê os seus números. Nenhuma outra locadora enxerga os seus dados, e o assistente de IA só consulta, nunca altera nada.'],
            ['p' => 'Posso reenviar a planilha depois?', 'r' => 'Pode, quando quiser. Cada envio fica no histórico e nada é sobrescrito. Se o arquivo for idêntico a um que você já enviou, o sistema avisa e não grava de novo.'],
        ],

        'titulo_pagina' => 'Oravel Intelligence: veja onde sua locadora está perdendo dinheiro',
        'descricao_pagina' => 'Envie sua planilha e receba a leitura da sua locadora em minutos: equipamentos parados, manutenção cara e o que vender, trocar ou reformar. Primeira leitura grátis.',
    ],
];
