<?php

use App\Filament\Pages\AgendaTecnico;
use App\Filament\Pages\Almoxarifado;
use App\Filament\Pages\CrmAgenda;
use App\Filament\Pages\CrmFunil;
use App\Filament\Pages\CrmMetasPainel;
use App\Filament\Pages\MaintenanceKanban;
use App\Filament\Pages\ProgramacaoLogistica;
use App\Filament\Resources\AbsenceResource;
use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ContractMeasurementResource;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\CrmContaResource;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmPropostaResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EpiDeliveryResource;
use App\Filament\Resources\FleetDriverResource;
use App\Filament\Resources\FleetVehicleResource;
use App\Filament\Resources\FreightRecordResource;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\MaintenancePlanResource;
use App\Filament\Resources\MaterialRequestResource;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\PropostaComercialResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\QuoteResource;
use App\Filament\Resources\WarehouseResource;

/**
 * Guia dos módulos: depois que o cliente conclui os "Primeiros passos", a mesma tela passa a mostrar as telas mais importantes de cada
 * produto contratado. Cada tela só aparece se o usuário pode acessá-la (plano e permissão). Chave = código do pacote (config/pacotes.php).
 * `recurso` = Resource do Filament; `pagina` = Page do Filament. `icone` = heroicon.
 */
return [
    'manutencao' => [
        ['titulo' => 'Quadro de ordens de serviço', 'descricao' => 'Veja as OS por etapa e o que está parado.', 'icone' => 'heroicon-o-view-columns', 'pagina' => MaintenanceKanban::class],
        ['titulo' => 'Ordens de serviço', 'descricao' => 'Abra, acompanhe e conclua cada ordem.', 'icone' => 'heroicon-o-wrench-screwdriver', 'recurso' => MaintenanceOrderResource::class],
        ['titulo' => 'Equipamentos', 'descricao' => 'Cadastro, histórico e etiqueta com QR Code.', 'icone' => 'heroicon-o-cube', 'recurso' => AssetResource::class],
        ['titulo' => 'Plano preventivo', 'descricao' => 'Defina de quanto em quanto tempo cada equipamento revisa.', 'icone' => 'heroicon-o-calendar-days', 'recurso' => MaintenancePlanResource::class],
        ['titulo' => 'Agenda do técnico', 'descricao' => 'Compromissos e OS agendadas da equipe.', 'icone' => 'heroicon-o-clock', 'pagina' => AgendaTecnico::class],
    ],
    'servicos' => [
        ['titulo' => 'Propostas comerciais', 'descricao' => 'Monte, envie e acompanhe o aceite do cliente.', 'icone' => 'heroicon-o-document-text', 'recurso' => PropostaComercialResource::class],
        ['titulo' => 'Orçamentos', 'descricao' => 'Orçamentos de serviço com valores e prazos.', 'icone' => 'heroicon-o-calculator', 'recurso' => QuoteResource::class],
        ['titulo' => 'Clientes', 'descricao' => 'Cadastro e histórico de cada cliente.', 'icone' => 'heroicon-o-users', 'recurso' => ClientResource::class],
        ['titulo' => 'Contratos', 'descricao' => 'Contratos de serviço gerados das propostas aceitas.', 'icone' => 'heroicon-o-clipboard-document-check', 'recurso' => ContractResource::class],
        ['titulo' => 'Ordens de serviço', 'descricao' => 'Execução e acompanhamento do trabalho.', 'icone' => 'heroicon-o-wrench-screwdriver', 'recurso' => MaintenanceOrderResource::class],
        ['titulo' => 'Contas a receber', 'descricao' => 'Cobranças e recebimentos de cada serviço.', 'icone' => 'heroicon-o-banknotes', 'recurso' => AccountReceivableResource::class],
    ],
    'vendas' => [
        ['titulo' => 'Funil de vendas', 'descricao' => 'Todos os leads por etapa, num só quadro.', 'icone' => 'heroicon-o-funnel', 'pagina' => CrmFunil::class],
        ['titulo' => 'Leads', 'descricao' => 'Lista completa com contato e próximo passo.', 'icone' => 'heroicon-o-user-group', 'recurso' => CrmLeadResource::class],
        ['titulo' => 'Agenda comercial', 'descricao' => 'Visitas e ligações combinadas.', 'icone' => 'heroicon-o-calendar', 'pagina' => CrmAgenda::class],
        ['titulo' => 'Contas', 'descricao' => 'Empresas atendidas e seus contatos.', 'icone' => 'heroicon-o-building-office', 'recurso' => CrmContaResource::class],
        ['titulo' => 'Propostas', 'descricao' => 'Propostas enviadas e o resultado de cada uma.', 'icone' => 'heroicon-o-document-text', 'recurso' => CrmPropostaResource::class],
        ['titulo' => 'Metas', 'descricao' => 'Desempenho do time contra as metas.', 'icone' => 'heroicon-o-flag', 'pagina' => CrmMetasPainel::class],
    ],
    'locadora' => [
        ['titulo' => 'Contratos de locação', 'descricao' => 'Contratos, vigência e valores.', 'icone' => 'heroicon-o-clipboard-document-check', 'recurso' => ContractResource::class],
        ['titulo' => 'Medições', 'descricao' => 'Medições e faturamento de cada contrato.', 'icone' => 'heroicon-o-chart-bar', 'recurso' => ContractMeasurementResource::class],
        ['titulo' => 'Equipamentos', 'descricao' => 'Frota de equipamentos, status e histórico.', 'icone' => 'heroicon-o-cube', 'recurso' => AssetResource::class],
        ['titulo' => 'Ordens de serviço', 'descricao' => 'Manutenção e vistorias dos equipamentos.', 'icone' => 'heroicon-o-wrench-screwdriver', 'recurso' => MaintenanceOrderResource::class],
        ['titulo' => 'Clientes', 'descricao' => 'Cadastro e histórico de cada cliente.', 'icone' => 'heroicon-o-users', 'recurso' => ClientResource::class],
        ['titulo' => 'Contas a receber', 'descricao' => 'Cobranças e recebimentos.', 'icone' => 'heroicon-o-banknotes', 'recurso' => AccountReceivableResource::class],
    ],
    'estoque' => [
        ['titulo' => 'Gestão de estoque', 'descricao' => 'Saldos, entradas e saídas num só lugar.', 'icone' => 'heroicon-o-archive-box', 'pagina' => Almoxarifado::class],
        ['titulo' => 'Materiais e peças', 'descricao' => 'Cadastro e saldo de cada item.', 'icone' => 'heroicon-o-cube', 'recurso' => MaterialResource::class],
        ['titulo' => 'Requisições', 'descricao' => 'Pedidos de material feitos pela equipe.', 'icone' => 'heroicon-o-clipboard-document-list', 'recurso' => MaterialRequestResource::class],
        ['titulo' => 'Pedidos de compra', 'descricao' => 'Compras e recebimentos.', 'icone' => 'heroicon-o-shopping-cart', 'recurso' => PurchaseOrderResource::class],
        ['titulo' => 'Almoxarifados', 'descricao' => 'Locais de armazenamento e transferências.', 'icone' => 'heroicon-o-building-storefront', 'recurso' => WarehouseResource::class],
    ],
    'logistica' => [
        ['titulo' => 'Programação', 'descricao' => 'Saídas, rotas e agenda de motoristas.', 'icone' => 'heroicon-o-map', 'pagina' => ProgramacaoLogistica::class],
        ['titulo' => 'Veículos', 'descricao' => 'Frota, documentos e manutenção.', 'icone' => 'heroicon-o-truck', 'recurso' => FleetVehicleResource::class],
        ['titulo' => 'Motoristas', 'descricao' => 'Cadastro e habilitações.', 'icone' => 'heroicon-o-identification', 'recurso' => FleetDriverResource::class],
        ['titulo' => 'Fretes', 'descricao' => 'Fretes e transportes registrados.', 'icone' => 'heroicon-o-arrows-right-left', 'recurso' => FreightRecordResource::class],
    ],
    'departamento_pessoal' => [
        ['titulo' => 'Colaboradores', 'descricao' => 'Cadastro, cargos e documentos.', 'icone' => 'heroicon-o-users', 'recurso' => EmployeeResource::class],
        ['titulo' => 'Faltas e atestados', 'descricao' => 'Ausências registradas e justificadas.', 'icone' => 'heroicon-o-calendar-days', 'recurso' => AbsenceResource::class],
        ['titulo' => 'Entrega de EPI', 'descricao' => 'Entregas e conformidade de EPI.', 'icone' => 'heroicon-o-shield-check', 'recurso' => EpiDeliveryResource::class],
    ],
];
