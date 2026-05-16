<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceOrderResource\Pages;
use App\Models\MaintenanceOrder;
use App\Models\Asset;
use App\Models\User;
use App\Models\Client;
use App\Models\Material;
use App\Models\ChatRoom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;
use App\Traits\HasMaintenanceStyles;

class MaintenanceOrderResource extends Resource
{
    use HasMaintenanceStyles;

    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $model = MaintenanceOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Ordens de Serviço';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?int $navigationSort = 2;

    protected static ?string $tenantRelationshipName = 'maintenanceOrders';
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function getRecordRouteKeyName(): ?string { return 'id'; }

    public static function canViewAny(): bool { return true; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('timer_display')
                ->label('Tempo em Execução')
                ->columnSpanFull()
                ->content(function ($record) {
                    if (!$record || $record->status !== 'Em Andamento' || !$record->last_timer_start) return null;
                    return new HtmlString("<div class='flex items-center gap-3 text-primary-600 bg-primary-50 p-4 rounded-xl border border-primary-100' x-data='{ time: \"00:00:00\", start: {$record->last_timer_start->timestamp}, accumulated: {$record->total_time_seconds}, update() { const now = Math.floor(Date.now() / 1000); const total = (now - this.start) + this.accumulated; const h = Math.floor(total / 3600).toString().padStart(2, \"0\"); const m = Math.floor((total % 3600) / 60).toString().padStart(2, \"0\"); const s = (total % 60).toString().padStart(2, \"0\"); this.time = h + \":\" + m + \":\" + s; } }' x-init='update(); setInterval(() => update(), 1000)'><div class='relative flex h-3 w-3'><span class='animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75'></span><span class='relative inline-flex rounded-full h-3 w-3 bg-primary-500'></span></div><span class='text-2xl font-mono font-bold tracking-wider' x-text='time'></span><span class='text-sm font-medium uppercase text-gray-500 ml-2'>Trabalhando agora</span></div>");
                })->visible(fn ($record) => $record && $record->status === 'Em Andamento'),

            Forms\Components\Tabs::make('Fluxo Oravel')->tabs([
                Forms\Components\Tabs\Tab::make('Dados Gerais')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('asset_id')
                            ->label('Ativo / QR Code')
                            ->placeholder('Bipe o código ou digite Pat/Série/Tag')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->getSearchResultsUsing(function (string $search) {
                                $tenant = Filament::getTenant();
                                $tenantId = $tenant?->id;
                                if (!$tenantId) return [];

                                return Asset::where('tenant_id', $tenantId)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                          ->orWhere('patrimonio', 'like', "%{$search}%");
                                        if (Schema::hasColumn('assets', 'serial_number')) {
                                            $q->orWhere('serial_number', 'like', "%{$search}%");
                                        }
                                        if (Schema::hasColumn('assets', 'asset_tag')) {
                                            $q->orWhere('asset_tag', 'like', "%{$search}%");
                                        }
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($asset) => [$asset->id => "{$asset->name} [Pat: {$asset->patrimonio}]"]);
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => Asset::find($value)?->name)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state) {
                                    $asset = Asset::find($state);
                                    if ($asset) {
                                        $set('checklists', Asset::getDefaultChecklist($asset?->asset_category ?? 'default'));
                                        $set('horimetro_anterior', $asset->last_horimetro ?? 0);
                                    }
                                }
                            })
                            ->prefixIcon('heroicon-m-qr-code'),

                        Forms\Components\Select::make('maintenance_type')
                            ->label('Tipo de Operação')
                            ->options([
                                'Check-in' => 'Check-in (Mobilização)',
                                'Check-out' => 'Check-out (Desmobilização)',
                                'Preventiva' => 'Manutenção Preventiva',
                                'Corretiva' => 'Manutenção Corretiva',
                            ])->required()->native(false)->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $asset = Asset::find($get('asset_id'));
                                if ($asset) {
                                    $set('checklists', Asset::getDefaultChecklist($asset?->asset_category ?? 'default'));
                                }
                            }),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('horimetro_anterior')
                            ->label('Hor. Anterior')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(fn(Get $get) => $get('horimetro_anterior') ?? '0.00'),

                        Forms\Components\TextInput::make('horimetro_entry')
                            ->label('Horímetro Atual')
                            ->numeric()
                            ->default(0) 
                            ->required()
                            ->prefixIcon('heroicon-m-clock'),
                        
                        Forms\Components\Select::make('fuel_level')
                            ->label('Nível Combustível')
                            ->options(['0'=>'Reserva', '25'=>'1/4', '50'=>'1/2', '75'=>'3/4', '100'=>'Cheio'])
                            ->native(false),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('technician_id')
                            ->label('Responsável Técnico')
                            ->options(function() {
                                $tenant = Filament::getTenant();
                                return $tenant ? User::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                            })
                            ->required()->searchable(),

                        Forms\Components\Select::make('client_id')
                            ->label('Cliente / Localidade')
                            ->relationship('client', 'name', function(Builder $query) {
                                $tenant = Filament::getTenant();
                                return $tenant ? $query->where('tenant_id', $tenant->id) : $query;
                            })
                            ->searchable(),
                    ]),

                    Forms\Components\Select::make('status')
                        ->options(['Aberto' => 'Aberto', 'Pendente' => 'Pendente', 'Em Andamento' => 'Em Andamento', 'Concluída' => 'Concluída', 'Cancelada' => 'Cancelada'])
                        ->default('Aberto')->disabled()
                ]),

                Forms\Components\Tabs\Tab::make('Checklist / Dossiê')->schema([
                    Forms\Components\Repeater::make('checklists')
                        ->relationship('checklists')
                        ->schema([
                            Forms\Components\TextInput::make('item')
                                ->label('Item de Verificação')
                                ->readOnly()
                                ->extraAttributes(fn ($state) => str_contains($state, '---') 
                                    ? ['style' => 'font-weight: bold; color: #0284c7; background-color: #f0f9ff; border-left: 4px solid #0284c7; padding-left: 10px;'] 
                                    : []
                                )
                                ->columnSpan(2),
                            
                            Forms\Components\Toggle::make('status')
                                ->label('OK')
                                ->onColor('success')
                                ->disabled(fn (Get $get) => str_contains($get('item'), '---'))
                                ->default(true),

                            Forms\Components\TextInput::make('observation')
                                ->label('Obs / Avaria')
                                ->placeholder('Detalhes se houver irregularidade'),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->columns(4),
                ]),

                Forms\Components\Tabs\Tab::make('Materiais')
                    ->visible(fn (Get $get) => in_array($get('maintenance_type'), ['Preventiva', 'Corretiva']))
                    ->schema([
                        Forms\Components\Repeater::make('materials')
                            ->relationship('materials')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->relationship('material', 'name', function(Builder $query) {
                                        $tenant = Filament::getTenant();
                                        return $tenant ? $query->where('tenant_id', $tenant->id) : $query;
                                    })
                                    ->required()->searchable()->live()
                                    ->afterStateUpdated(fn ($state, Set $set) => $set('unit_price', Material::find($state)?->price ?? 0)),
                                Forms\Components\TextInput::make('quantity')->label('Qtd')->numeric()->default(1)->live(),
                                Forms\Components\TextInput::make('unit_price')->label('Vlr Unit')->prefix('R$')->readOnly(),
                            ])->columns(3),
                    ]),

                Forms\Components\Tabs\Tab::make('Apontamentos')->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('pedir_peca')
                            ->label('Solicitar Peça (Pausar OS)')
                            ->color('danger')
                            ->icon('heroicon-o-puzzle-piece')
                            ->action(function ($record) {
                                $record->update(['internal_status' => 'aguardando_peca']);
                                
                                // AJUSTE DE INTEGRAÇÃO: Garante o registro do alerta sistêmico na tabela unificada de ChatRoom
                                $chatRoom = ChatRoom::firstOrCreate(
                                    [
                                        'maintenance_order_id' => $record->id,
                                        'tenant_id' => Filament::getTenant()?->id ?? auth()->user()->tenant_id,
                                    ],
                                    [
                                        'type' => 'maintenance',
                                        'title' => "Chat da OS: " . substr($record->id, 0, 8),
                                    ]
                                );

                                $chatRoom->messages()->create([
                                    'user_id' => auth()->id(),
                                    'message' => "🚨 Peça solicitada via PWA.",
                                ]);
                            })->visible(fn($record) => $record && $record->internal_status === 'em_manutencao'),
                    ]),

                    // COMPONENTE DO CHAT REMOVIDO DAQUI

                    Forms\Components\Textarea::make('technical_notes')
                        ->label(fn (Get $get) => in_array($get('maintenance_type'), ['Check-in', 'Check-out']) ? 'Notas de Mobilização / Observações de Recebimento' : 'Laudo Técnico da Manutenção')
                        ->rows(5)->columnSpanFull(),
                    
                    Forms\Components\SpatieMediaLibraryFileUpload::make('evidences')
                        ->label('Fotos / Evidências do Ativo')
                        ->collection('os_evidences')
                        ->multiple()
                        ->reorderable()
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(2)->schema([
                        SignaturePad::make('technician_signature')->label('Assinatura Técnico'),
                        SignaturePad::make('client_signature')->label('Assinatura Cliente'),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('Financeiro')
                    ->visible(fn (Get $get) => in_array($get('maintenance_type'), ['Preventiva', 'Corretiva']))
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('labor_cost')
                                ->label('Mão de Obra')
                                ->prefix('R$')
                                ->numeric()
                                ->default(0), 
                            
                            Forms\Components\TextInput::make('material_cost')
                                ->label('Peças/Insumos')
                                ->prefix('R$')
                                ->readOnly()
                                ->default(0), 
                            
                            Forms\Components\TextInput::make('total_order_cost')
                                ->label('Total Geral')
                                ->prefix('R$')
                                ->readOnly()
                                ->default(0), 
                        ]),
                    ]),
            ])->columnSpanFull()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('os_number')->label('Nº OS')->searchable()->weight('bold')->color('primary'),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo / Equipamento')
                    ->description(fn($record) => "Pat: {$record->asset?->patrimonio}")
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('internal_status')
                    ->label('Status Pátio')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'aguardando_diagnostico' => 'gray',
                        'em_manutencao' => 'warning',
                        'aguardando_peca' => 'danger',
                        'teste_qualidade' => 'info',
                        'disponivel_comercial' => 'success',
                        default => 'gray'
                    })->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('client.name')->label('Cliente'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 
                    'Aberto' => 'info', 'Em Andamento' => 'primary', 'Concluída' => 'success', 'Cancelada' => 'danger', default => 'gray' 
                }),
                Tables\Columns\TextColumn::make('technician.name')->label('Técnico'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('capacity')
                    ->label('Capacidade/Modelo')
                    ->options(function () {
                        $tenant = Filament::getTenant();
                        if (!$tenant || !Schema::hasTable('fleet_status')) return [];
                        return DB::table('fleet_status')
                            ->where('tenant_id', $tenant->id)
                            ->whereNotNull('capacity_label')
                            ->distinct()
                            ->pluck('capacity_label', 'capacity_label');
                    })
                    ->query(function (Builder $query, array $data) {
                        if ($data['value']) {
                            $query->whereHas('asset', fn($q) => $q->whereExists(
                                fn($sub) => $sub->select(DB::raw(1))
                                    ->from('fleet_status')
                                    ->whereColumn('fleet_status.asset_id', 'assets.id')
                                    ->where('fleet_status.capacity_label', $data['value'])
                            ));
                        }
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('approve_service')
                    ->label('Aprovar e Liberar')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->action(fn(MaintenanceOrder $record) => $record->update(['internal_status' => 'disponivel_comercial']))
                    ->visible(fn(MaintenanceOrder $record) => $record->internal_status === 'teste_qualidade'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Visualizar'),
                ])->icon('heroicon-m-ellipsis-vertical'),
            ]);
    }

    public static function getPages(): array 
    { 
        return [
            'index' => Pages\ListMaintenanceOrders::route('/'), 
            'create' => Pages\CreateMaintenanceOrder::route('/create'), 
            'edit' => Pages\EditMaintenanceOrder::route('/{record}/edit'), 
        ]; 
    }
}