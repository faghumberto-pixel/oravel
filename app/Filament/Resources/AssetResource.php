<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CriticalityLevel;
use App\Models\EquipmentReplacement;
use App\Models\StorageLocation;
use App\Services\CepGeocodingService;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class AssetResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $modelLabel = 'Ativo';

    protected static ?string $pluralModelLabel = 'Ativos';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 1;

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function getNavigationBadge(): ?string
    {
        return 'G A';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Asset::class);
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('create', Asset::class);
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->can('update', $record);
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->can('delete', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Detalhes do Ativo')
                ->tabs([
                    // ABA 1: INFORMAÇÕES GERAIS E ROI (Auditável)
                    Tabs\Tab::make('Informações Gerais')
                        ->icon('heroicon-m-information-circle')
                        ->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('patrimonio')
                                    ->label('Nº Patrimônio')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->prefixIcon('heroicon-m-hashtag'),

                                // asset_category_id (2026-07-24): FK real pra AssetCategory --
                                // asset_category (texto) é mantido em paralelo só por
                                // compatibilidade com telas antigas que ainda leem o texto
                                // (coluna/filtro da tabela, exports, AssetDossier). Sem essa
                                // sincronia, a busca de disponibilidade por categoria em
                                // SolicitacaoLocacaoResource não teria como funcionar.
                                Forms\Components\Select::make('asset_category_id')
                                    ->label('Categoria e Tipo')
                                    // Não-obrigatório de propósito: o backfill da migration
                                    // 2026_07_24_143615 deixou boa parte dos ativos existentes
                                    // sem match seguro (nome do texto livre não batia com
                                    // nenhuma AssetCategory) -- exigir aqui travaria a edição
                                    // de qualquer campo desses ativos até alguém reclassificar.
                                    ->relationship('category', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $categoryName = $state ? AssetCategory::find($state)?->name : null;
                                        $set('asset_category', $categoryName);
                                        $set('checklist', Asset::getDefaultChecklist($categoryName));
                                    }),

                                Forms\Components\Hidden::make('asset_category'),

                                Forms\Components\Select::make('checklist_group_id')
                                    ->label('Grupo')
                                    ->helperText('Define o checklist básico aplicado às OS deste ativo e o template de manutenção preventiva herdado (aba "Planos de Manutenção").')
                                    ->relationship('checklistGroup', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->label('Nome do Grupo')->required(),
                                        Forms\Components\Textarea::make('description')->label('Objetivo')->rows(2),
                                    ]),

                                Forms\Components\TextInput::make('name')
                                    ->label('Nome/Modelo')
                                    ->required(),
                            ]),

                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('capacity_value')
                                    ->label('Capacidade')
                                    ->numeric()
                                    ->helperText('Ex: 250 para um gerador de 250 kVA.'),

                                Forms\Components\Select::make('capacity_unit')
                                    ->label('Unidade')
                                    ->options([
                                        'kVA' => 'kVA',
                                        'HP' => 'HP',
                                        'toneladas' => 'toneladas',
                                        'kg' => 'kg',
                                        'm³' => 'm³',
                                        'PCM' => 'PCM',
                                        'L' => 'L',
                                        'outro' => 'outro',
                                    ])
                                    ->native(false),

                                Forms\Components\TextInput::make('specification')
                                    ->label('Especificação Adicional')
                                    ->helperText('Texto livre — detalhes que não cabem em capacidade estruturada.'),
                            ]),

                            Forms\Components\Section::make('Investimento e Depreciação')
                                ->description('Calculado automaticamente com base na vida útil informada.')
                                ->compact()
                                ->schema([
                                    Forms\Components\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('acquisition_value')
                                            ->label('Valor de Aquisição')
                                            ->numeric()
                                            ->prefix('R$')
                                            ->live()
                                            ->required(),

                                        Forms\Components\DatePicker::make('acquisition_date')
                                            ->label('Data de Aquisição')
                                            ->live()
                                            ->required(),

                                        Forms\Components\TextInput::make('residual_value')
                                            ->label('Valor Residual')
                                            ->numeric()
                                            ->prefix('R$')
                                            ->live()
                                            ->default(0),

                                        Forms\Components\TextInput::make('useful_life_years')
                                            ->label('Vida Útil (anos)')
                                            ->numeric()
                                            ->live()
                                            ->default(5),
                                    ]),

                                    Forms\Components\Placeholder::make('depreciation_display')
                                        ->label('Indicador de Depreciação')
                                        ->content(function (Get $get) {
                                            $acquisitionValue = (float) ($get('acquisition_value') ?? 0);
                                            $residualValue = (float) ($get('residual_value') ?? 0);
                                            $usefulLifeYears = (int) ($get('useful_life_years') ?? 0);
                                            $acquisitionDate = $get('acquisition_date');

                                            if ($acquisitionValue <= 0 || $usefulLifeYears <= 0 || ! $acquisitionDate) {
                                                return new HtmlString('<span class="text-gray-400">Preencha os campos acima para calcular.</span>');
                                            }

                                            $depreciableAmount = max($acquisitionValue - $residualValue, 0);
                                            $monthlyDepreciation = $depreciableAmount / ($usefulLifeYears * 12);
                                            $monthsElapsed = Carbon::parse($acquisitionDate)->diffInMonths(now());
                                            $accumulated = min($monthlyDepreciation * $monthsElapsed, $depreciableAmount);
                                            $currentValue = $acquisitionValue - $accumulated;
                                            $percentage = $depreciableAmount > 0 ? round(($accumulated / $depreciableAmount) * 100, 1) : 0;

                                            return new HtmlString(
                                                "<div class='flex gap-6 text-sm'>".
                                                "<div><span class='text-gray-400'>Valor Atual:</span> <b>R$ ".number_format($currentValue, 2, ',', '.').'</b></div>'.
                                                "<div><span class='text-gray-400'>Depreciado:</span> <b>R$ ".number_format($accumulated, 2, ',', '.').'</b></div>'.
                                                "<div><span class='text-gray-400'>Percentual:</span> <b>{$percentage}%</b></div>".
                                                '</div>'
                                            );
                                        }),
                                ]),

                            Forms\Components\Section::make('Controle de Vida Útil e ROI')
                                ->description('Ajustes manuais nestes campos ficam registrados nos Logs de Auditoria.')
                                ->compact()
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('horimetro_inicial')
                                            ->label('Horímetro de Aquisição')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Permitido correção de erro de digitação na entrada.')
                                            ->prefixIcon('heroicon-m-flag')
                                            ->visible(fn () => Tenancy::current()?->hasModuleEnabled('horimetro_rigido') ?? true),

                                        Forms\Components\TextInput::make('last_horimetro')
                                            ->label('Leitura Atual (Sistema)')
                                            ->numeric()
                                            ->readOnly() // Permite gravação pelo sistema, mas protege contra digitação acidental
                                            ->helperText('Sincronizado automaticamente pelas Ordens de Serviço.')
                                            ->prefixIcon('heroicon-m-arrow-path')
                                            ->visible(fn () => Tenancy::current()?->hasModuleEnabled('horimetro_rigido') ?? true),

                                        Forms\Components\Select::make('status')
                                            ->label('Status Operacional')
                                            ->options([
                                                Asset::STATUS_DISPONIVEL => 'Disponível',
                                                Asset::STATUS_LOCADO => 'Locado',
                                                Asset::STATUS_MANUTENCAO => 'Em Manutenção',
                                                Asset::STATUS_OPERANDO => 'Em Operação',
                                                Asset::STATUS_AGUARDANDO_TRIAGEM => 'Aguardando Triagem',
                                            ])
                                            ->default(Asset::STATUS_DISPONIVEL)
                                            ->required(),
                                    ]),
                                ]),
                        ]),

                    // ABA: LOCALIZAÇÃO -- internal_unit_id existia desde
                    // 2026-05 mas sem relacao no model nem uso em nenhum
                    // form; agora e' a base "onde o ativo mora" quando nao
                    // tem contrato vigente. Quando existe um Contract ativo
                    // de verdade (Asset::activeContract()), a fonte de
                    // verdade e' ele (Contract::resolvedLocation()), so
                    // leitura aqui -- editar isso e' no ContractResource.
                    // Checagem e' pela EXISTENCIA do contrato ativo, nao
                    // pelo campo Asset.status (editavel a mao, podia ficar
                    // dessincronizado do contrato de verdade).
                    Tabs\Tab::make('Localização')
                        ->icon('heroicon-m-map-pin')
                        ->schema([
                            Forms\Components\Select::make('internal_unit_id')
                                ->label('Unidade/Filial Base')
                                ->relationship('internalUnit', 'name')
                                ->helperText('Onde o ativo fica baseado quando NÃO está locado (pátio/matriz/filial).')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Forms\Components\Select::make('storage_location_id')
                                ->label('Posição no Pátio (Planta Baixa)')
                                ->relationship(
                                    'storageLocation',
                                    'code',
                                    fn ($query, Get $get) => $query
                                        ->where('context', StorageLocation::CONTEXT_PATIO_ATIVOS)
                                        ->when($get('internal_unit_id'), fn ($q, $unitId) => $q->where('internal_unit_id', $unitId))
                                )
                                ->searchable()
                                ->preload(),

                            Forms\Components\TextInput::make('cep')
                                ->label('CEP do Equipamento')
                                ->placeholder('00000-000')
                                ->live(onBlur: true)
                                ->helperText('Usado pra plotar este equipamento no Mapa de Equipamentos.')
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if (! $state) {
                                        return;
                                    }

                                    $service = app(CepGeocodingService::class);
                                    $endereco = $service->lookupCep($state);

                                    if (! $endereco) {
                                        Notification::make()->title('CEP não encontrado.')->warning()->send();

                                        return;
                                    }

                                    $fullAddress = trim($endereco['address'].', '.$endereco['city'].' - '.$endereco['uf']);
                                    $set('endereco', $fullAddress);
                                    $coords = $service->geocodeAddress($fullAddress);

                                    if ($coords) {
                                        $set('latitude', $coords['latitude']);
                                        $set('longitude', $coords['longitude']);
                                        Notification::make()->title('Equipamento localizado no mapa.')->success()->send();
                                    } else {
                                        $set('latitude', null);
                                        $set('longitude', null);
                                        Notification::make()->title('CEP encontrado, mas não foi possível localizar no mapa automaticamente.')->warning()->send();
                                    }
                                }),
                            Forms\Components\Hidden::make('endereco'),
                            Forms\Components\Hidden::make('latitude'),
                            Forms\Components\Hidden::make('longitude'),

                            Forms\Components\Placeholder::make('localizacao_atual_display')
                                ->label('Localização Atual')
                                ->content(function (?Asset $record) {
                                    if (! $record) {
                                        return new HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
                                    }

                                    $activeContract = $record->activeContract();

                                    if ($activeContract) {
                                        $location = $activeContract->resolvedLocation();

                                        if (! $location) {
                                            return new HtmlString('<span class="text-gray-400">Ativo com contrato vigente, mas sem localização definida nele.</span>');
                                        }

                                        $endereco = trim(($location['address'] ?? '').', '.($location['city'] ?? '').' - '.($location['uf'] ?? ''), ', -');

                                        return new HtmlString('<strong>'.e($location['label']).'</strong> (via contrato) — '.e($endereco ?: 'endereço não preenchido'));
                                    }

                                    $unit = $record->internalUnit;

                                    if (! $unit) {
                                        return new HtmlString('<span class="text-gray-400">Ativo não locado e sem unidade base definida acima.</span>');
                                    }

                                    $endereco = trim(($unit->address ?? '').', '.($unit->city ?? '').' - '.($unit->state ?? ''), ', -');

                                    return new HtmlString('<strong>'.e($unit->name).'</strong> (base) — '.e($endereco ?: 'endereço não preenchido'));
                                })
                                ->columnSpanFull(),
                        ]),

                    // ABA 2: RASTREABILIDADE (QR CODE)
                    Tabs\Tab::make('Rastreabilidade e QR Code')
                        ->icon('heroicon-m-qr-code')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('tag')
                                    ->label('Asset Tag (Etiqueta)')
                                    ->placeholder('TAG-0000')
                                    ->prefixIcon('heroicon-m-tag'),

                                Forms\Components\TextInput::make('serial_number')
                                    ->label('Número de Série')
                                    ->placeholder('S/N do Fabricante')
                                    ->prefixIcon('heroicon-m-identification'),
                            ]),

                            Forms\Components\Placeholder::make('qr_code_display')
                                ->label('Identificação Digital (Bipe no Campo)')
                                ->content(function ($record) {
                                    if (! $record) {
                                        return 'O QR Code será gerado após o primeiro salvamento.';
                                    }
                                    // Antes apontava pra /admin/assets/{id} sem /edit -- rota
                                    // que nunca existiu (sempre dava 404 ao escanear). Agora
                                    // aponta pra versao mobile do dossie (celular no campo/patio,
                                    // uso mais comum de quem escaneia), nao a tela de edicao.
                                    $url = route('assets.dossier.mobile', ['assetId' => $record->id]);

                                    return new HtmlString("
                                        <div class='flex flex-col items-center p-4 bg-white border border-gray-200 rounded-xl shadow-sm w-fit'>
                                            <div class='bg-white p-2'>
                                                <img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={$url}' alt='QR Code' />
                                            </div>
                                            <span class='text-[10px] mt-2 font-mono text-gray-400 uppercase tracking-widest'>Digital Asset ID</span>
                                            <span class='text-xs font-bold text-primary-600'>{$record->patrimonio}</span>
                                        </div>
                                    ");
                                })->visible(fn ($record) => $record !== null),
                        ]),

                    // ABA 3: HISTÓRICO DE TRABALHO
                    Tabs\Tab::make('Histórico de Trabalho')
                        ->icon('heroicon-m-clock')
                        ->schema([
                            Forms\Components\Repeater::make('maintenanceOrders')
                                ->relationship('maintenanceOrders')
                                ->label('Histórico de Operações e Manutenções')
                                ->schema([
                                    Forms\Components\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('os_number')->label('Nº OS')->disabled(),
                                        Forms\Components\TextInput::make('maintenance_type')->label('Tipo')->disabled(),
                                        Forms\Components\TextInput::make('horimetro_entry')->label('Horímetro')->disabled(),
                                        Forms\Components\TextInput::make('status')->label('Status')->disabled(),
                                    ]),
                                    Forms\Components\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('labor_cost')->label('Mão de Obra (R$)')->prefix('R$')->disabled(),
                                        Forms\Components\TextInput::make('material_cost')->label('Material (R$)')->prefix('R$')->disabled(),
                                        Forms\Components\TextInput::make('logistics_cost')->label('Logística (R$)')->prefix('R$')->disabled(),
                                        Forms\Components\TextInput::make('total_order_cost')->label('Custo Total (R$)')->prefix('R$')->disabled(),
                                    ]),
                                    Forms\Components\Textarea::make('technical_notes')
                                        ->label('Laudo/Notas Técnicas')
                                        ->disabled()
                                        ->rows(2),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->itemLabel(fn (array $state): ?string => isset($state['created_at'])
                                    ? 'Registro de '.Carbon::parse($state['created_at'])->format('d/m/Y H:i')
                                    : null
                                )
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('contracts')
                                ->relationship('contracts')
                                ->label('Histórico de Locações')
                                ->schema([
                                    Forms\Components\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('contract_number')->label('Nº Contrato')->disabled(),
                                        Forms\Components\TextInput::make('start_date')->label('Início')->disabled(),
                                        Forms\Components\TextInput::make('end_date')->label('Fim')->disabled(),
                                        Forms\Components\TextInput::make('price')->label('Valor (R$)')->prefix('R$')->disabled(),
                                    ]),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('equipment_replacement_history')
                                ->label('Histórico de Substituição de Equipamento')
                                ->content(fn (?Asset $record) => static::renderReplacementHistory($record))
                                ->columnSpanFull(),

                            Forms\Components\Placeholder::make('financial_summary')
                                ->label('Resumo Financeiro')
                                ->content(function ($record) {
                                    if (! $record) {
                                        return new HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
                                    }
                                    $s = $record->getFinancialSummary();
                                    $resultColor = $s['result'] >= 0 ? '#16a34a' : '#dc2626';
                                    $fmt = fn ($v) => number_format($v, 2, ',', '.');

                                    return new HtmlString(
                                        "<div class='grid grid-cols-3 gap-4 text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-lg'>".
                                        "<div><span class='text-gray-400'>Valor de Aquisição:</span><br><b>R\$ {$fmt($s['acquisition_value'])}</b></div>".
                                        "<div><span class='text-gray-400'>Valor Atual (Depreciado):</span><br><b>R\$ {$fmt($s['current_value'])}</b></div>".
                                        "<div><span class='text-gray-400'>Depreciação Acumulada:</span><br><b>R\$ {$fmt($s['accumulated_depreciation'])} ({$s['depreciation_percentage']}%)</b></div>".
                                        "<div><span class='text-gray-400'>Custo Mão de Obra:</span><br><b>R\$ {$fmt($s['total_labor_cost'])}</b></div>".
                                        "<div><span class='text-gray-400'>Custo Material:</span><br><b>R\$ {$fmt($s['total_material_cost'])}</b></div>".
                                        "<div><span class='text-gray-400'>Custo Logística:</span><br><b>R\$ {$fmt($s['total_logistics_cost'])}</b></div>".
                                        "<div><span class='text-gray-400'>Custo Total Manutenção:</span><br><b>R\$ {$fmt($s['total_maintenance_cost'])}</b></div>".
                                        "<div><span class='text-gray-400'>Receita Total de Locações:</span><br><b>R\$ {$fmt($s['total_rental_revenue'])}</b></div>".
                                        "<div><span class='text-gray-400'>Resultado:</span><br><b style='color: {$resultColor}'>R\$ {$fmt($s['result'])}</b></div>".
                                        '</div>'
                                    );
                                })
                                ->columnSpanFull(),
                        ]),

                    // ABA 4: LOGS DE AUDITORIA (Rastreabilidade de Ações)
                    Tabs\Tab::make('Logs de Auditoria')
                        ->icon('heroicon-m-finger-print')
                        ->schema([
                            Forms\Components\Placeholder::make('activities_history')
                                ->label('Histórico de Alterações no Cadastro')
                                ->content(fn (?Asset $record) => static::renderActivityHistory($record))
                                ->columnSpanFull(),
                        ]),

                    // ABA 5: CHECKLIST DINÂMICO
                    Tabs\Tab::make('Checklist de Verificação')
                        ->icon('heroicon-m-clipboard-document-check')
                        ->schema([
                            Forms\Components\Repeater::make('checklist')
                                ->label('Itens de Inspeção')
                                ->schema([
                                    Forms\Components\TextInput::make('item')
                                        ->label('Descrição do Item')
                                        ->required()
                                        ->columnSpan(3),
                                    Forms\Components\Toggle::make('status')
                                        ->label('OK')
                                        ->default(true)
                                        ->columnSpan(1),
                                ])
                                ->columns(4)
                                ->createItemButtonLabel('Adicionar Item Extra')
                                ->reorderable(true),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),

                Tables\Columns\TextColumn::make('patrimonio')
                    ->label('Patrimônio')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Asset $record): string => 'S/N: '.($record->serial_number ?? 'N/A')),

                Tables\Columns\TextColumn::make('name')
                    ->label('Equipamento')
                    ->searchable()
                    ->description(fn (Asset $record): string => 'Tag: '.($record->tag ?? '---')),

                Tables\Columns\TextColumn::make('last_horimetro')
                    ->label('Horímetro Atual')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('asset_category')
                    ->label('Categoria')
                    ->badge(),

                Tables\Columns\TextColumn::make('checklistGroup.name')
                    ->label('Grupo')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sem grupo'),

                Tables\Columns\TextColumn::make('capacity_value')
                    ->label('Capacidade')
                    ->sortable()
                    ->formatStateUsing(fn (Asset $record) => $record->capacity_value !== null
                        ? rtrim(rtrim(number_format((float) $record->capacity_value, 2, ',', '.'), '0'), ',').' '.($record->capacity_unit ?? '')
                        : null)
                    ->placeholder('Não informado'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => Asset::statusColor($state)),

                Tables\Columns\TextColumn::make('abcMatrix.nivel')
                    ->label('Criticidade')
                    ->badge()
                    ->formatStateUsing(fn (Asset $record): string => $record->currentCriticalityLevel()?->name ?? $record->abcMatrix?->nivel ?? '—')
                    ->color(fn (Asset $record): string|array => $record->currentCriticalityLevel()?->color
                        ? Color::hex($record->currentCriticalityLevel()->color)
                        : 'gray')
                    ->placeholder('Não definida'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'disponivel' => 'Disponível',
                        'locado' => 'Locado',
                        'manutencao' => 'Em Manutenção',
                        'operando' => 'Em Operação',
                        'aguardando_triagem' => 'Aguardando Triagem',
                        'quarentena' => 'Quarentena',
                        'reservado' => 'Reservado',
                    ]),
                Tables\Filters\SelectFilter::make('abc_matrix_nivel')
                    ->label('Criticidade')
                    ->options(fn () => CriticalityLevel::orderBy('sort_order')->pluck('name', 'code'))
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $nivel) => $q->whereHas('abcMatrix', fn ($q2) => $q2->where('nivel', $nivel))
                    )),
                Tables\Filters\SelectFilter::make('asset_category')
                    ->label('Categoria')
                    ->options(fn () => AssetCategory::pluck('name', 'name')),
                Tables\Filters\SelectFilter::make('checklist_group_id')
                    ->label('Grupo')
                    ->relationship('checklistGroup', 'name'),
                Tables\Filters\SelectFilter::make('capacity_unit')
                    ->label('Unidade de Capacidade')
                    ->options([
                        'kVA' => 'kVA',
                        'HP' => 'HP',
                        'toneladas' => 'toneladas',
                        'kg' => 'kg',
                        'm³' => 'm³',
                        'PCM' => 'PCM',
                        'L' => 'L',
                        'outro' => 'outro',
                    ]),
                Tables\Filters\Filter::make('capacity_range')
                    ->label('Faixa de Capacidade')
                    ->form([
                        Forms\Components\TextInput::make('capacity_min')
                            ->label('De')
                            ->numeric(),
                        Forms\Components\TextInput::make('capacity_max')
                            ->label('Até')
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['capacity_min'] ?? null, fn ($q, $min) => $q->where('capacity_value', '>=', $min))
                            ->when($data['capacity_max'] ?? null, fn ($q, $max) => $q->where('capacity_value', '<=', $max));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('qr_patio')
                    ->label('QR do Pátio')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (Asset $record): string => route('assets.qr', ['asset' => $record->id]))
                    ->openUrlInNewTab(),
                // Atalho direto pro dossie mobile (celular) deste ativo --
                // antes so' dava pra chegar la escaneando o QR fisico ou
                // entrando em Editar > aba Rastreabilidade. E' de la que o
                // tecnico registra o horimetro e, se o ativo estiver
                // locado, copia o link publico pro funcionario do cliente.
                Tables\Actions\Action::make('dossie_horimetro')
                    ->label('Dossiê / Horímetro')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn (Asset $record): string => route('assets.dossier.mobile', ['assetId' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('alterar_status_em_massa')
                        ->label('Alterar Status em Massa')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Novo Status')
                                ->options([
                                    Asset::STATUS_DISPONIVEL => 'Disponível',
                                    Asset::STATUS_LOCADO => 'Locado',
                                    Asset::STATUS_MANUTENCAO => 'Em Manutenção',
                                    Asset::STATUS_OPERANDO => 'Em Operação',
                                    Asset::STATUS_AGUARDANDO_TRIAGEM => 'Aguardando Triagem',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['status' => $data['status']]);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AssetResource\RelationManagers\ChecklistItemsRelationManager::class,
            AssetResource\RelationManagers\PatioArrivalsRelationManager::class,
            AssetResource\RelationManagers\MaintenancePlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    /**
     * Historico de troca de equipamento deste ativo, nos dois sentidos (ele
     * foi substituido, ou ele foi o substituto de outro) -- mesmo
     * diagnostico/OS/tecnico/datas que tambem aparecem no historico do
     * Contrato (ContractResource), aqui filtrado por ativo.
     */
    private static function renderReplacementHistory(?Asset $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
        }

        $asOriginal = $record->equipmentReplacementsAsOriginal()
            ->with(['replacementAsset', 'maintenanceOrder', 'requestedBy'])
            ->get()
            ->map(fn (EquipmentReplacement $r) => [$r, 'original']);

        $asReplacement = $record->equipmentReplacementsAsReplacement()
            ->with(['originalAsset', 'maintenanceOrder', 'requestedBy'])
            ->get()
            ->map(fn (EquipmentReplacement $r) => [$r, 'replacement']);

        $entries = $asOriginal->concat($asReplacement)->sortByDesc(fn ($entry) => $entry[0]->created_at);

        if ($entries->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">Nenhuma troca de equipamento envolvendo este ativo.</span>');
        }

        $statusLabels = [
            EquipmentReplacement::STATUS_SOLICITADO => ['Solicitado', '#6b7280'],
            EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO => ['Substituto Identificado', '#0ea5e9'],
            EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO => ['Desmobilização em Andamento', '#d97706'],
            EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO => ['Mobilização em Andamento', '#d97706'],
            EquipmentReplacement::STATUS_CONCLUIDO => ['Concluído', '#16a34a'],
            EquipmentReplacement::STATUS_CANCELADO => ['Cancelado', '#dc2626'],
        ];

        $rows = $entries->map(function (array $entry) use ($statusLabels) {
            [$r, $direction] = $entry;
            [$statusLabel, $color] = $statusLabels[$r->status] ?? [$r->status, '#6b7280'];
            $os = $r->maintenanceOrder;

            $direcaoLabel = $direction === 'original'
                ? 'Substituído por: '.e($r->replacementAsset?->name ?? 'ainda não definido')
                : 'Substituiu: '.e($r->originalAsset?->name ?? '—');

            return "<tr style='border-top:1px solid rgba(156,163,175,0.2)'>".
                "<td style='padding:8px 12px'>{$direcaoLabel}</td>".
                "<td style='padding:8px 12px'>".e($r->reason).'</td>'.
                "<td style='padding:8px 12px'>".e($os?->os_number ?? '—').'</td>'.
                "<td style='padding:8px 12px'>".e($r->requestedBy?->name ?? '—').'</td>'.
                "<td style='padding:8px 12px'><span style='color:{$color};font-weight:600'>{$statusLabel}</span></td>".
                "<td style='padding:8px 12px'>".e($r->created_at->format('d/m/Y H:i')).'</td>'.
                '</tr>';
        })->implode('');

        return new HtmlString(
            "<div style='overflow-x:auto'><table style='width:100%;font-size:13px;border-collapse:collapse'>".
            '<thead><tr style="text-align:left;color:#9ca3af">'.
            '<th style="padding:8px 12px">Movimentação</th>'.
            '<th style="padding:8px 12px">Diagnóstico</th>'.
            '<th style="padding:8px 12px">OS</th>'.
            '<th style="padding:8px 12px">Técnico</th>'.
            '<th style="padding:8px 12px">Status</th>'.
            '<th style="padding:8px 12px">Solicitado em</th>'.
            "</tr></thead><tbody>{$rows}</tbody></table></div>"
        );
    }

    /**
     * Regressao: essa aba era um Repeater com ->relationship('activities'),
     * o que faz o Filament tentar sincronizar (salvar) cada Activity
     * existente ao salvar o form do Ativo. O campo 'causer.name' (notacao
     * de ponto, so pra exibir o nome via relacao) deixava um 'causer' => []
     * vazando no payload de update mesmo desabilitado -- e como
     * Spatie\Activitylog\Models\Activity tem $guarded = [], o Eloquent
     * tentava atualizar uma coluna "causer" que nao existe (a coluna real e
     * causer_type/causer_id), quebrando TODA edicao de um ativo que ja
     * tivesse pelo menos 1 log. Trocado por Placeholder somente-leitura,
     * que nunca tenta gravar nada de volta.
     */
    private static function renderActivityHistory(?Asset $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
        }

        $activities = $record->activities()->with('causer')->latest()->get();

        if ($activities->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">Nenhuma alteração registrada para este ativo.</span>');
        }

        $actionLabels = [
            'created' => 'Criação',
            'updated' => 'Atualização',
            'deleted' => 'Exclusão',
        ];

        $rows = $activities->map(function ($activity) use ($actionLabels) {
            $action = $actionLabels[$activity->description] ?? $activity->description;
            $causerName = $activity->causer?->name ?? '—';
            $newValues = collect($activity->properties?->get('attributes') ?? [])
                ->map(fn ($value, $key) => e($key).': '.e(is_scalar($value) ? $value : json_encode($value)))
                ->implode('<br>');

            return "<tr style='border-top:1px solid rgba(156,163,175,0.2)'>".
                "<td style='padding:8px 12px'>".e($action).'</td>'.
                "<td style='padding:8px 12px'>".e($causerName).'</td>'.
                "<td style='padding:8px 12px'>".e($activity->created_at->format('d/m/Y H:i')).'</td>'.
                "<td style='padding:8px 12px;font-size:12px'>".($newValues ?: '—').'</td>'.
                '</tr>';
        })->implode('');

        return new HtmlString(
            "<div style='overflow-x:auto'><table style='width:100%;font-size:13px;border-collapse:collapse'>".
            '<thead><tr style="text-align:left;color:#9ca3af">'.
            '<th style="padding:8px 12px">Ação</th>'.
            '<th style="padding:8px 12px">Usuário</th>'.
            '<th style="padding:8px 12px">Data/Hora</th>'.
            '<th style="padding:8px 12px">Valores Alterados</th>'.
            "</tr></thead><tbody>{$rows}</tbody></table></div>"
        );
    }
}
