<?php

use App\Filament\Pages\Almoxarifado;
use App\Filament\Pages\CrmFunil;
use App\Filament\Pages\MaintenanceKanban;
use App\Filament\Pages\ProgramacaoLogistica;

/**
 * Pacotes vendáveis do Oravel. Cada pacote é um conjunto de módulos montado pelos MESMOS grupos de menu da
 * tela de Contratos (módulo novo num grupo entra no pacote sozinho) mais chaves extras explícitas.
 * Preço NÃO fica aqui: é cadastrado/editado na Central, no contrato de vitrine do pacote (`pacotes:vitrine`). `grupos` = '*' liga todos os grupos (ERP completo). A "base" entra em todos os pacotes.
 */
return [
    // Códigos antigos de pacote → código atual (o pacote "CRM" passou a se chamar "Vendas").
    'aliases' => ['crm' => 'vendas'],

    // Complementos (cobrança automática, notas fiscais) são vendidos à parte: nenhum pacote os inclui, nem o ERP completo.
    'grupos_complementos' => ['Complementos'],

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

        'vendas' => [
            'nome' => 'Vendas',
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

        'manutencao' => [
            'nome' => 'Manutenção',
            'descricao' => 'Manutenção de equipamentos: ativos, ordens de serviço, preventiva (PMP), checklists e itens agregados — sem contratos, financeiro nem comercial.',
            'grupos' => ['Ativos', 'Manutenção', 'PMP', 'Itens Agregados'],
            // Clientes simples para a OS; avarias e substituição de equipamento são de locação.
            'features' => ['tabela_clients'],
            'excluir' => ['tabela_equipment_damages', 'tabela_equipment_replacements'],
            'inicio' => MaintenanceKanban::class,
        ],

        'servicos' => [
            'nome' => 'Serviços',
            'descricao' => 'Prestação de serviços: clientes, orçamentos, propostas, ordens de serviço, contratos de serviço e financeiro — sem locação de equipamentos.',
            'grupos' => ['Ativos', 'Manutenção', 'Financeiro'],
            'features' => [
                'tabela_clients', 'tabela_quotes', 'tabela_proposta_comercial', 'tabela_contracts', 'assinatura_eletronica', 'tabela_client_feedbacks',
            ],
            'excluir' => ['tabela_equipment_damages', 'tabela_equipment_replacements'],
            'inicio' => MaintenanceKanban::class,
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
