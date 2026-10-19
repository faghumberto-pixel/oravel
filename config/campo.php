<?php

use App\Models\CrmLead;
use App\Models\CrmVisita;
use App\Models\MaintenanceOrder;

/**
 * Área de campo (/campo): atalhos grandes para celular. Cada item pertence a um produto (config/pacotes.php) e herda a cor dele.
 * Aparece só se a empresa tem o produto/módulo e o usuário pode ver o modelo (a política já checa plano e permissão).
 * `rota` = nome de rota existente (o campo reaproveita as telas de celular que já existem; nada é duplicado).
 * `perfis` = quem vê: tecnico (executa OS) e/ou vendedor (CRM); administrador vê todos.
 */
return [
    'itens' => [
        ['chave' => 'os_rapida', 'titulo' => 'OS rápida', 'descricao' => 'Abrir uma ordem agora', 'icone' => 'heroicon-o-plus-circle',
            'produto' => 'manutencao', 'rota' => 'os-rapida.mobile', 'modelo' => MaintenanceOrder::class, 'perfis' => ['tecnico'], 'acao' => 'create'],
        ['chave' => 'minhas_os', 'titulo' => 'Minhas OS do dia', 'descricao' => 'O que fazer hoje', 'icone' => 'heroicon-o-wrench-screwdriver',
            'produto' => 'manutencao', 'rota' => 'filament.admin.pages.technician-daily-tasks', 'modelo' => MaintenanceOrder::class, 'perfis' => ['tecnico']],
        ['chave' => 'agenda_tecnico', 'titulo' => 'Agenda', 'descricao' => 'Próximos compromissos', 'icone' => 'heroicon-o-calendar-days',
            'produto' => 'manutencao', 'rota' => 'agenda-tecnico.mobile', 'modelo' => MaintenanceOrder::class, 'perfis' => ['tecnico']],
        ['chave' => 'leads', 'titulo' => 'Meus leads', 'descricao' => 'Retornos e contatos', 'icone' => 'heroicon-o-user-group',
            'produto' => 'vendas', 'rota' => 'crm.leads.mobile', 'modelo' => CrmLead::class, 'perfis' => ['vendedor']],
        ['chave' => 'visitas', 'titulo' => 'Visitas e ligações', 'descricao' => 'Registrar e agendar', 'icone' => 'heroicon-o-map-pin',
            'produto' => 'vendas', 'rota' => 'crm.visitas.mobile', 'modelo' => CrmVisita::class, 'perfis' => ['vendedor']],
    ],
];
