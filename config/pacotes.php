<?php

use App\Filament\Pages\Almoxarifado;
use App\Filament\Pages\CrmFunil;
use App\Filament\Pages\ProgramacaoLogistica;

/**
 * Pacotes vendáveis do Oravel. Cada pacote é um conjunto de módulos montado pelos MESMOS grupos de menu da
 * tela de Contratos (módulo novo num grupo entra no pacote sozinho) mais chaves extras explícitas.
 * `grupos` = '*' liga todos os grupos (ERP completo). A "base" entra em todos os pacotes.
 */
return [
    'base' => [
        'grupos' => ['Configurações', 'Equipe'],
        'features' => ['modulo_dashboard'],
    ],

    'pacotes' => [
        'locadora' => [
            'nome' => 'Locadora',
            'descricao' => 'ERP completo para locadoras: ativos, manutenção, contratos, estoque, logística, frota, financeiro e CRM.',
            'grupos' => '*',
            'features' => [],
            'inicio' => null, // painel inicial de sempre
        ],

        'crm' => [
            'nome' => 'CRM',
            'descricao' => 'CRM de vendas independente do ERP: leads, funil, contas, catálogo, visitas, veículos, despesas, metas e propostas.',
            'grupos' => [],
            'features' => [
                'menu_gestao_crm', 'menu_crm_agenda', 'menu_crm_mapa',
                'tabela_crm_leads', 'tabela_crm_funis', 'tabela_crm_etapas', 'tabela_crm_motivos_perda', 'tabela_crm_origens',
                'tabela_crm_campos_custom', 'tabela_crm_contas', 'tabela_crm_produtos', 'tabela_crm_visitas',
                'tabela_crm_veiculos', 'tabela_crm_despesas', 'tabela_crm_metas', 'tabela_crm_propostas',
            ],
            'inicio' => CrmFunil::class,
        ],

        'estoque' => [
            'nome' => 'Estoque',
            'descricao' => 'Gestão de estoque: materiais e peças, almoxarifado, compras, inventário e transferências.',
            'grupos' => ['Materiais e Peças'],
            'features' => [],
            'inicio' => Almoxarifado::class,
        ],

        'logistica' => [
            'nome' => 'Logística',
            'descricao' => 'Logística e frota: veículos, fretes, pátio, rastreamento, rotas, checklists, abastecimento e custos.',
            'grupos' => ['Logística', 'Gestão de Frota'],
            'features' => [],
            'inicio' => ProgramacaoLogistica::class,
        ],
    ],
];
