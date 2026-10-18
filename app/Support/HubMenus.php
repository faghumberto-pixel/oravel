<?php

namespace App\Support;

/** Texto de apresentação de cada página de menu pai: para quem abre o menu pela primeira vez saber para que ele serve. */
class HubMenus
{
    public const DESCRICOES = [
        'Almoxarifado' => 'Controle o estoque de materiais e peças: saldos por filial, movimentações, localizações no almoxarifado, transferências e análises para evitar falta de peças.',
        'GestaoAtivos' => 'Cadastre e acompanhe os equipamentos da empresa: categorias, grupos, histórico do patrimônio, localização no mapa e na planta do pátio.',
        'GestaoComercial' => 'Tudo da venda e da locação: clientes, propostas, orçamentos, contratos, medições, franquias de horas e solicitações de locação.',
        'GestaoCompras' => 'Do pedido ao recebimento: fornecedores, requisições e ordens de compra, recebimentos e reposição de estoque.',
        'GestaoCrm' => 'Organize as vendas: leads, funil, contas, visitas, metas e propostas, com agenda e mapa comercial. Importe planilhas e configure o funil.',
        'GestaoEpi' => 'Controle os equipamentos de proteção individual: compras, entrega aos colaboradores e conformidade com a NR-6.',
        'GestaoInsumos' => 'Controle os insumos e consumíveis usados nos equipamentos: tipos, entradas por compra e saídas para cada equipamento.',
        'GestaoItensAgregados' => 'Acessórios e componentes que acompanham os equipamentos: cadastro dos itens e dos tipos, entradas por compra e saídas para equipamento.',
        'LogisticaFretesTransporte' => 'Gerencie fretes e transportadoras: solicitações de retirada, histórico de movimentações e otimização de rotas com inteligência artificial.',
        'LogisticaFrota' => 'Veículos, motoristas e rastreamento: planos de manutenção, GPS, percursos, quilometragem e vencimentos dos veículos.',
        'LogisticaPatio' => 'Acompanhe o pátio e os depósitos: chegadas de equipamentos, aprovações e a programação do dia.',
        'MaintenancaoAnaliseIa' => 'Análises de manutenção feitas com inteligência artificial, como o retrabalho em ordens de serviço corretivas.',
        'MaintenancaoCadastros' => 'Cadastros de apoio da manutenção: checklists, avarias, trocas de equipamento, histórico de paradas, matriz ABC e níveis de criticidade.',
        'MaintenancaoOperacao' => 'O dia a dia da manutenção: ordens de serviço, kanban do pátio, programação, reservas urgentes e apontamento de horímetro.',
        'RelatoriosAnalises' => 'Análises para decidir melhor: avarias e reincidência, carga de técnicos, desempenho e retrabalho, e a Central de IA.',
        'RelatoriosHistoricosLogs' => 'Consulte o histórico da operação: eventos e falhas, alterações, status das ordens de serviço, matriz ABC, horímetro e notificações.',
        'RelatoriosPaineis' => 'Painéis e relatórios para acompanhar a operação: criticidade dos equipamentos, SLA e relatórios gerais.',
    ];

    public static function descricao(object|string $pagina): string
    {
        return self::DESCRICOES[class_basename($pagina)] ?? 'Escolha uma das opções abaixo para começar.';
    }
}
