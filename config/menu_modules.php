<?php

/**
 * Módulos de MENU: cada menu pai (Relatórios > Análises, Gestão de Estoque...) e página
 * sem tabela própria vira uma chave de contrato, para a Central poder ligar/desligar
 * o menu inteiro. `herda` lista os módulos que já liberavam esse menu antes de ele ter
 * chave própria: a migração liga a chave nos contratos que tinham algum deles (ou em
 * todos, quando a lista é vazia), para ninguém perder menu ao publicar.
 */
return [
    'menu_almoxarifado' => [
        'label' => 'Menu: Materiais e Peças → Gestão de Estoque',
        'grupo' => 'Materiais e Peças',
        'menu' => 'Gestão de Estoque',
        'slug' => 'almoxarifado',
        'herda' => ['tabela_internal_units', 'tabela_material_categories', 'tabela_material_location_stock', 'tabela_materials', 'tabela_material_stock_takes', 'tabela_parts', 'tabela_storage_locations', 'tabela_warehouses', 'ia_diagnostico_avarias', 'tabela_material_stock_movements'],
    ],
    'menu_compliance_status' => [
        'label' => 'Página: Status de Conformidade',
        'grupo' => 'Configurações',
        'menu' => 'Status de Conformidade',
        'slug' => 'compliance-status',
        'herda' => [],
    ],
    'menu_gestao_ativos' => [
        'label' => 'Menu: Ativos → Gestão de Ativos',
        'grupo' => 'Ativos',
        'menu' => 'Gestão de Ativos',
        'slug' => 'gestao-ativos',
        'herda' => ['tabela_asset_categories', 'tabela_assets', 'tabela_checklist_groups'],
    ],
    'menu_gestao_comercial' => [
        'label' => 'Menu: Comercial → Gestão Comercial',
        'grupo' => 'Comercial',
        'menu' => 'Gestão Comercial',
        'slug' => 'gestao-comercial',
        'herda' => ['tabela_clients', 'tabela_contract_measurements', 'tabela_contracts', 'assinatura_eletronica', 'tabela_fleet_statuses', 'tabela_proposta_comercial', 'tabela_quotes', 'tabela_rental_hour_franchises', 'tabela_rental_overage_charges', 'tabela_solicitacao_locacao'],
    ],
    'menu_gestao_compras' => [
        'label' => 'Menu: Materiais e Peças → Gestão de Compras',
        'grupo' => 'Materiais e Peças',
        'menu' => 'Gestão de Compras',
        'slug' => 'gestao-compras',
        'herda' => ['tabela_goods_receipts', 'tabela_material_requests', 'tabela_parts_requests', 'tabela_purchase_orders', 'tabela_suppliers'],
    ],
    'menu_gestao_crm' => [
        'label' => 'Menu: Comercial → Gestão CRM',
        'grupo' => 'Comercial',
        'menu' => 'Gestão CRM',
        'slug' => 'gestao-crm',
        'herda' => ['tabela_crm_leads', 'ia_diagnostico_avarias'],
    ],
    'menu_gestao_epi' => [
        'label' => 'Menu: EPI → Gestão de EPI',
        'grupo' => 'EPI',
        'menu' => 'Gestão de EPI',
        'slug' => 'gestao-epi',
        'herda' => ['tabela_epi_deliveries', 'tabela_epi_entries'],
    ],
    'menu_gestao_insumos' => [
        'label' => 'Menu: Itens Agregados → Insumos e Consumíveis',
        'grupo' => 'Itens Agregados',
        'menu' => 'Insumos e Consumíveis',
        'slug' => 'gestao-insumos',
        'herda' => ['tabela_aggregate_item_entries', 'tabela_aggregate_item_exits', 'tabela_aggregate_item_types'],
    ],
    'menu_gestao_itens_agregados' => [
        'label' => 'Menu: Itens Agregados → Acessórios e Componentes',
        'grupo' => 'Itens Agregados',
        'menu' => 'Acessórios e Componentes',
        'slug' => 'gestao-itens-agregados',
        'herda' => ['tabela_aggregate_item_entries', 'tabela_aggregate_item_exits', 'tabela_aggregate_items', 'tabela_aggregate_item_types'],
    ],
    'menu_logistica_fretes_transporte' => [
        'label' => 'Menu: Logística → Fretes & Transporte',
        'grupo' => 'Logística',
        'menu' => 'Fretes & Transporte',
        'slug' => 'logistica-fretes-transporte',
        'herda' => ['tabela_equipment_movements', 'tabela_equipment_pickup_requests', 'tabela_freight_carriers', 'tabela_freight_records', 'ia_diagnostico_avarias'],
    ],
    'menu_logistica_frota' => [
        'label' => 'Menu: Logística → Frota',
        'grupo' => 'Logística',
        'menu' => 'Frota',
        'slug' => 'logistica-frota',
        'herda' => ['tabela_fleet_drivers', 'tabela_fleet_maintenance_plans', 'tabela_fleet_vehicles', 'tabela_traccar_devices'],
    ],
    'menu_logistica_patio' => [
        'label' => 'Menu: Logística → Pátio',
        'grupo' => 'Logística',
        'menu' => 'Pátio',
        'slug' => 'logistica-patio',
        'herda' => ['tabela_depots', 'tabela_equipment_movements'],
    ],
    'menu_maintenancao_analise_ia' => [
        'label' => 'Menu: Manutenção → Análise (IA)',
        'grupo' => 'Manutenção',
        'menu' => 'Análise (IA)',
        'slug' => 'maintenancao-analise-ia',
        'herda' => ['ia_diagnostico_avarias'],
    ],
    'menu_maintenancao_cadastros' => [
        'label' => 'Menu: Manutenção → Cadastros',
        'grupo' => 'Manutenção',
        'menu' => 'Cadastros',
        'slug' => 'maintenancao-cadastros',
        'herda' => ['tabela_abc_matrix', 'tabela_asset_downtime_events', 'tabela_checklist_templates', 'tabela_criticality_levels', 'tabela_equipment_damages', 'tabela_equipment_replacements'],
    ],
    'menu_maintenancao_operacao' => [
        'label' => 'Menu: Manutenção → Operação',
        'grupo' => 'Manutenção',
        'menu' => 'Operação',
        'slug' => 'maintenancao-operacao',
        'herda' => ['tabela_maintenance_orders', 'tabela_preventive_maintenance_executions', 'tabela_horimeter_readings'],
    ],
    'menu_relatorios_analises' => [
        'label' => 'Menu: Relatórios → Análises',
        'grupo' => 'Relatórios',
        'menu' => 'Análises',
        'slug' => 'relatorios-analises',
        'herda' => ['ia_diagnostico_avarias', 'tabela_equipment_damages', 'tabela_maintenance_orders'],
    ],
    'menu_relatorios_historicos_logs' => [
        'label' => 'Menu: Relatórios → Históricos & Logs',
        'grupo' => 'Relatórios',
        'menu' => 'Históricos & Logs',
        'slug' => 'relatorios-historicos-logs',
        'herda' => ['tabela_abc_matrix_histories', 'tabela_activity_log_entries', 'tabela_horimeter_readings', 'tabela_maintenance_status_histories', 'tabela_notification_logs', 'tabela_equipment_damages'],
    ],
    'menu_relatorios_paineis' => [
        'label' => 'Menu: Relatórios → Painéis',
        'grupo' => 'Relatórios',
        'menu' => 'Painéis',
        'slug' => 'relatorios-paineis',
        'herda' => ['tabela_assets', 'tabela_maintenance_orders'],
    ],
];
