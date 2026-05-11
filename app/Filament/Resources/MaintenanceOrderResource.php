<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceOrderResource\Pages;
use App\Models\MaintenanceOrder;
use App\Models\Asset;
use App\Models\User;
use App\Models\Client;
use App\Models\Material;
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
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

class MaintenanceOrderResource extends Resource
{ 
    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $model = MaintenanceOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Ordens de Serviço';
    protected static ?string $navigationGroup = 'GESTAO DE MANUTENCAO'; 
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
                
                // ABA 1: MOBILIZAÇÃO E DADOS GERAIS
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
                                $query = Asset::where('tenant_id', Filament::getTenant()->id);

                                return $query->where(function ($q) use ($search) {
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
                                        $set('checklists', Asset::getDefaultChecklist($asset->asset_category));
                                        // Busca horímetro anterior para referência
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
                                    $set('checklists', Asset::getDefaultChecklist($asset->asset_category));
                                }
                            }),
                    ]),

                    // HORÍMETRO E COMBUSTÍVEL AGORA SEMPRE VISÍVEIS EM DADOS GERAIS
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
                            ->default(0) // Evita erro Not Null
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
                            ->options(fn() => User::where('tenant_id', Filament::getTenant()->id)->pluck('name', 'id'))
                            ->required()->searchable(),

                        Forms\Components\Select::make('client_id')
                            ->label('Cliente / Localidade')
                            ->relationship('client', 'name', fn(Builder $query) => $query->where('tenant_id', Filament::getTenant()->id))
                            ->searchable(),
                    ]),

                    Forms\Components\Select::make('status')
                        ->options(['Aberto' => 'Aberto', 'Pendente' => 'Pendente', 'Em Andamento' => 'Em Andamento', 'Concluída' => 'Concluída', 'Cancelada' => 'Cancelada'])
                        ->default('Aberto')->disabled()
                ]),

                // ABA 2: CHECKLIST / DOSSIÊ
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

                // ABA 3: MATERIAIS
                Forms\Components\Tabs\Tab::make('Materiais')->schema([
                    Forms\Components\Repeater::make('materials')
                        ->relationship('materials')
                        ->schema([
                            Forms\Components\Select::make('material_id')
                                ->relationship('material', 'name', fn(Builder $query) => $query->where('tenant_id', Filament::getTenant()->id))
                                ->required()->searchable()->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set('unit_price', Material::find($state)?->price ?? 0)),
                            Forms\Components\TextInput::make('quantity')->label('Qtd')->numeric()->default(1)->live(),
                            Forms\Components\TextInput::make('unit_price')->label('Vlr Unit')->prefix('R$')->readOnly(),
                        ])->columns(3),
                ]),

                // ABA 4: APONTAMENTOS E EVIDÊNCIAS
                Forms\Components\Tabs\Tab::make('Apontamentos')->schema([
                    Forms\Components\Textarea::make('technical_notes')
                        ->label('Laudo Técnico / Notas de Mobilização')
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

                // ABA 5: FINANCEIRO (AJUSTADO COM DEFAULT 0)
                Forms\Components\Tabs\Tab::make('Financeiro')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('labor_cost')
                            ->label('Mão de Obra')
                            ->prefix('R$')
                            ->numeric()
                            ->default(0), // Evita erro Not Null
                        
                        Forms\Components\TextInput::make('material_cost')
                            ->label('Peças/Insumos')
                            ->prefix('R$')
                            ->readOnly()
                            ->default(0), // Evita erro Not Null
                        
                        Forms\Components\TextInput::make('total_order_cost')
                            ->label('Total Geral')
                            ->prefix('R$')
                            ->readOnly()
                            ->default(0), // Evita erro Not Null
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
                    ->placeholder('Ativo Não Encontrado')
                    ->description(function ($record): string {
                        if (!$record->asset) return "N/A";
                        $desc = "Pat: {$record->asset->patrimonio}";
                        if (Schema::hasColumn('assets', 'serial_number') && $record->asset->serial_number) {
                            $desc .= " | S/N: {$record->asset->serial_number}";
                        }
                        return $desc;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('asset', function ($q) use ($search) {
                            $q->where('patrimonio', 'like', "%{$search}%")
                              ->orWhere('name', 'like', "%{$search}%");
                            
                            if (Schema::hasColumn('assets', 'serial_number')) {
                                $q->orWhere('serial_number', 'like', "%{$search}%");
                            }
                            if (Schema::hasColumn('assets', 'asset_tag')) {
                                $q->orWhere('asset_tag', 'like', "%{$search}%");
                            }
                        });
                    }),

                Tables\Columns\TextColumn::make('client.name')->label('Cliente')->placeholder('Uso Interno'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 
                    'Aberto' => 'info', 'Em Andamento' => 'primary', 'Concluída' => 'success', 'Cancelada' => 'danger', default => 'gray' 
                }),
                Tables\Columns\TextColumn::make('technician.name')->label('Técnico'),
            ])
            ->actions([
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