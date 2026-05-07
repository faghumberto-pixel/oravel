<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceOrderResource\Pages;
use App\Filament\Resources\MaintenanceOrderResource\Widgets\MaintenanceOrderStats;
use App\Filament\Resources\MaintenanceOrderResource\Widgets\MaintenanceOrderVolumeChart;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderDelegation; 
use App\Models\Asset;
use App\Models\User;
use App\Models\Client;
use App\Models\Branch;
use App\Models\ReportedProblem;
use App\Models\MaintenanceOrderChecklist;
use App\Models\CriticalityLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;
use Illuminate\Support\HtmlString;
use Filament\Support\Colors\Color;
use App\Filament\Resources\MaintenanceOrderResource\RelationManagers;
use Filament\Notifications\Notification;

class MaintenanceOrderResource extends Resource
{
    protected static ?string $model = MaintenanceOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Ordens de Serviço';
    protected static ?string $navigationGroup = '--- ATIVOS E PCM ---'; 
    protected static ?int $navigationSort = 2; 

    public static function getEloquentQuery(): Builder
    {
        return MaintenanceOrder::query()
            ->with(['asset.checklistGroup', 'checklists', 'materials', 'client', 'branch', 'technician', 'reportedProblem', 'criticalityLevel', 'delegation', 'evidences'])
            ->where('tenant_id', Auth::user()->tenant_id);
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

            Tabs::make('Fluxo Oravel')->tabs([
                Tabs\Tab::make('Dados Gerais')->schema([
                    Forms\Components\Select::make('asset_id')
                        ->label('Ativo / Equipamento')
                        ->options(Asset::query()->where('tenant_id', Auth::user()->tenant_id)->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->name} | Pat: {$a->patrimonio}"]))
                        ->required()->searchable()->live()->disabled(fn ($record) => $record !== null)->prefixIcon('heroicon-m-cube')
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $asset = Asset::find($state);
                            if ($asset) {
                                if ($get('maintenance_type') === MaintenanceOrder::TYPE_CHECKIN && $asset->status === 'Em Cliente') {
                                    Notification::make()->title('Conflito Logístico')->body("Ativo {$asset->name} já consta 'Em Cliente'.")->warning()->persistent()->send();
                                }
                                $set('criticality_level_id', $asset->criticality_level_id);
                                if ($asset->checklist_group_id) {
                                    $templates = MaintenanceOrderChecklist::withoutGlobalScopes()->where('checklist_group_id', $asset->checklist_group_id)->where('is_template', true)->where('is_completed', false)->get();
                                    $set('checklists', $templates->map(fn($t) => ['category' => $t->category ?? 'Preventiva', 'section' => $t->section, 'item_name' => $t->item_name, 'is_completed' => false])->toArray());
                                }
                            }
                        }),
                    Forms\Components\Section::make('Informações de Atendimento')->description('Logística e Alocação')->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('maintenance_type')->label('Natureza da Operação')->options([MaintenanceOrder::TYPE_CHECKIN => 'Check-in', MaintenanceOrder::TYPE_CHECKOUT => 'Check-out', MaintenanceOrder::TYPE_PREVENTIVE => 'Preventiva', MaintenanceOrder::TYPE_CORRECTIVE => 'Corretiva', 'Preditiva' => 'Preditiva', 'Inspeção' => 'Inspeção'])->required()->live()->native(false)->prefixIcon('heroicon-m-clipboard-document-check'),
                            Forms\Components\Select::make('service_type')->label('Local')->options(['Interno' => 'Interno', 'Externo' => 'Externo'])->default('Interno')->required()->live()->native(false)->prefixIcon('heroicon-m-map-pin'),
                            
                            // AJUSTE DEFINITIVO PARA O TÉCNICO APARECER
                            Forms\Components\Select::make('technician_id')
                                ->label('Técnico Responsável')
                                ->options(fn () => \App\Models\User::where('tenant_id', auth()->user()->tenant_id)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->native(false)
                                ->prefixIcon('heroicon-m-user-circle')
                                ->placeholder('Selecione o técnico disponível'),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('branch_id')->label('Unidade')->relationship('branch', 'name', fn (Builder $query) => $query->where('tenant_id', Auth::user()->tenant_id))->visible(fn (Get $get) => $get('service_type') === 'Interno')->required(fn (Get $get) => $get('service_type') === 'Interno')->searchable()->preload()->prefixIcon('heroicon-m-building-office-2'),
                            Forms\Components\Select::make('client_id')->label('Cliente')->relationship('client', 'name', fn (Builder $query) => $query->where('tenant_id', Auth::user()->tenant_id))->visible(fn (Get $get) => $get('service_type') === 'Externo')->searchable()->preload()->required(fn (Get $get) => $get('service_type') === 'Externo')->prefixIcon('heroicon-m-user'),
                            Forms\Components\TextInput::make('logistics_cost')->label('Custo Logística')->prefix('R$')->numeric()->default(0),
                        ]),
                    ]),
                    Forms\Components\Section::make('Laudo de Vistoria (Locação)')->visible(fn (Get $get) => in_array($get('maintenance_type'), [MaintenanceOrder::TYPE_CHECKOUT, MaintenanceOrder::TYPE_CHECKIN]))->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('horimeter')->label('Horímetro / KM')->numeric()->prefixIcon('heroicon-m-clock'),
                            Forms\Components\Select::make('fuel_level')->label('Combustível')->options(['Reserva'=>'Reserva','1/4'=>'1/4','1/2'=>'1/2','3/4'=>'3/4','Cheio'=>'Cheio'])->native(false),
                            Forms\Components\TextInput::make('cleanliness_status')->label('Limpeza'),
                        ]),
                        Forms\Components\Grid::make(2)->visible(fn (Get $get) => $get('maintenance_type') === MaintenanceOrder::TYPE_CHECKOUT)->schema([
                            Forms\Components\CheckboxList::make('damage_tags')->label('Avarias no Retorno')->options(['mecanico' => '🔧 Mecânico','eletrico' => '⚡ Elétrico','lataria' => '🚗 Lataria','pneus' => '🛞 Pneus','vidros' => '🪟 Vidros'])->columns(2),
                            Forms\Components\Textarea::make('technical_notes')->label('Dossiê de Integridade')->rows(4),
                        ]),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('status')->label('Status')->disabled()->dehydrated(),
                        Forms\Components\DateTimePicker::make('started_at')->label('Início Real')->disabled(),
                        Forms\Components\DateTimePicker::make('finished_at')->label('Fim Real')->disabled(),
                    ]),
                ]),

                Tabs\Tab::make('Delegado')->icon('heroicon-m-user-group')->schema([
                    Forms\Components\Placeholder::make('delegation_history')->content(fn ($record) => $record?->delegation ? "Delegado para {$record->delegation->technician->name} em " . $record->delegation->delegated_at->format('d/m/Y H:i') : "Aguardando delegação."),
                    Forms\Components\Textarea::make('delegation.supervisor_instructions')->label('Instruções')->disabled()->columnSpanFull(),
                ])->visible(fn ($record) => $record?->delegation !== null),

                Tabs\Tab::make('Checklist')->icon('heroicon-m-list-bullet')->hidden(fn (Get $get) => in_array($get('maintenance_type'), [MaintenanceOrder::TYPE_CHECKIN, MaintenanceOrder::TYPE_CHECKOUT]))->schema([
                    Forms\Components\Repeater::make('checklists')->relationship('checklists')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Hidden::make('category')->default('Preventiva'),
                                Forms\Components\TextInput::make('section')->label('Etapa')->disabled()->dehydrated(), 
                                Forms\Components\TextInput::make('item_name')->label('Item')->disabled()->dehydrated(), 
                                Forms\Components\Checkbox::make('is_completed')->label('Executado'),
                            ]),
                        ])->disableItemCreation()->disableItemDeletion(),
                ]),

                Tabs\Tab::make('Materiais')->icon('heroicon-m-cog')->hidden(fn (Get $get) => in_array($get('maintenance_type'), [MaintenanceOrder::TYPE_CHECKIN, MaintenanceOrder::TYPE_CHECKOUT]))->schema([
                    Forms\Components\Repeater::make('materials')->relationship('materials')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Material')->required(),
                            Forms\Components\TextInput::make('quantity')->label('Qtd')->numeric()->default(1)->live(),
                            Forms\Components\TextInput::make('unit_price')->label('Vlr Unitário')->prefix('R$')->numeric()->default(0)->live(),
                        ])->columns(3),
                ]),

                Tabs\Tab::make('Financeiro')->icon('heroicon-m-banknotes')->hidden(fn (Get $get) => in_array($get('maintenance_type'), [MaintenanceOrder::TYPE_CHECKIN, MaintenanceOrder::TYPE_CHECKOUT]))->schema([
                    Forms\Components\Section::make('Resumo PCM')->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Placeholder::make('labor_cost_p')->label('Mão de Obra')->content(fn ($record) => 'R$ ' . number_format($record?->labor_cost ?? 0, 2, ',', '.')),
                            Forms\Components\Placeholder::make('material_cost_p')->label('Materiais')->content(fn ($record) => 'R$ ' . number_format($record?->material_cost ?? 0, 2, ',', '.')),
                            Forms\Components\Placeholder::make('logistics_cost_p')->label('Logística')->content(fn ($record) => 'R$ ' . number_format($record?->logistics_cost ?? 0, 2, ',', '.')),
                            Forms\Components\Placeholder::make('total_cost_p')->label('Total OS')->content(fn ($record) => new HtmlString("<span class='text-xl font-bold text-primary-600'>R$ " . number_format($record?->total_order_cost ?? 0, 2, ',', '.') . "</span>")),
                        ]),
                    ]),
                ]),

                Tabs\Tab::make('Comunicação')->icon('heroicon-m-chat-bubble-left-right')->hidden(fn (Get $get) => in_array($get('maintenance_type'), [MaintenanceOrder::TYPE_CHECKIN, MaintenanceOrder::TYPE_CHECKOUT]))->schema([
                    Forms\Components\Repeater::make('internalCommunications')->relationship('internalCommunications')
                        ->schema([
                            Forms\Components\Placeholder::make('info')->label('Autor')->content(fn ($record) => $record ? ($record->user->name . ' em ' . $record->created_at->format('d/m/Y H:i')) : 'Nova mensagem'),
                            Forms\Components\Textarea::make('message')->label('Mensagem')->required(),
                            Forms\Components\Hidden::make('user_id')->default(auth()->id()),
                        ])->createItemButtonLabel('Adicionar Comentário')->columnSpanFull(),
                ]),

                Tabs\Tab::make('Apontamentos')->icon('heroicon-m-pencil-square')->schema([
                    Forms\Components\Textarea::make('technical_notes')->label('Laudo Técnico')->rows(6)->columnSpanFull(),
                    SignaturePad::make('client_signature')->label('Assinatura')->clearable()->columnSpanFull(),
                ]),

                Tabs\Tab::make('Dossiê GPS')->icon('heroicon-m-shield-check')->visible(fn (Get $get) => in_array($get('maintenance_type'), [MaintenanceOrder::TYPE_CHECKOUT, MaintenanceOrder::TYPE_CHECKIN]))->schema([
                    Forms\Components\SpatieMediaLibraryFileUpload::make('os_galeria')->collection('os_galeria')->label('Galeria')->multiple()->extraAttributes(['capture' => 'environment']),
                    Forms\Components\Repeater::make('evidences')->relationship('evidences')->label('Auditoria Satélite')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\FileUpload::make('file_path')->image()->disk('public')->directory('evidences'),
                                Forms\Components\Section::make('Metadados')->schema([
                                    Forms\Components\TextInput::make('address')->label('Local GPS')->disabled(),
                                    Forms\Components\DateTimePicker::make('captured_at')->label('Timestamp')->disabled(),
                                    Forms\Components\TextInput::make('formatted_location')->label('Coordenadas')->disabled(),
                                ]),
                            ]),
                        ])->disableItemCreation()->disableItemDeletion()->columns(1),
                ]),
            ])->columnSpanFull()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('os_number')->label('Nº OS')->searchable()->fontFamily('mono')->weight('bold')->color('primary'),
                Tables\Columns\TextColumn::make('asset.patrimonio')->label('Patrimônio')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 
                    'Aberto' => 'info', 
                    'Em Andamento' => 'primary', 
                    'Concluída' => 'success', 
                    default => 'gray' 
                }),
                Tables\Columns\TextColumn::make('maintenance_type')->label('Natureza da Operação')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('technician.name')->label('Técnico')->placeholder('Não alocado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['Aberto' => 'Aberto', 'Em Andamento' => 'Em Andamento', 'Concluída' => 'Concluída']),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\Action::make('print')->label('Laudo')->icon('heroicon-o-printer')->color('success')->url(fn ($record) => route('maintenance-orders.dossie.pdf', $record))->openUrlInNewTab()->visible(fn ($record) => in_array($record->maintenance_type, [MaintenanceOrder::TYPE_CHECKOUT, MaintenanceOrder::TYPE_CHECKIN])),
                Tables\Actions\Action::make('delegate')->label('Delegar')->icon('heroicon-m-user-plus')->color('warning')->form([
                    Forms\Components\Select::make('technician_id')
                        ->label('Mecânico')
                        ->options(fn () => User::where('tenant_id', Auth::user()->tenant_id)->pluck('name', 'id'))
                        ->required(),
                    Forms\Components\Textarea::make('supervisor_instructions')->required(),
                ])->action(function ($record, $data) {
                    MaintenanceOrderDelegation::create(['maintenance_order_id' => $record->id, 'technician_id' => $data['technician_id'], 'supervisor_instructions' => $data['supervisor_instructions'], 'delegated_at' => now()]);
                    $record->update(['technician_id' => $data['technician_id'], 'status' => 'Em Andamento']);
                })->visible(fn ($record) => $record->status === 'Aberto'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn () => (bool) auth()->user()->is_admin),
            ]);
    }

    public static function getWidgets(): array 
    { 
        return [
            MaintenanceOrderStats::class,
            MaintenanceOrderVolumeChart::class,
        ]; 
    }

    public static function getPages(): array 
    { 
        return [
            'index' => Pages\ListMaintenanceOrders::route('/'), 
            'create' => Pages\CreateMaintenanceOrder::route('/create'), 
            'edit' => Pages\EditMaintenanceOrder::route('/{record}/edit'), 
            'view' => Pages\ViewMaintenanceOrder::route('/{record}')
        ]; 
    }

    public static function getRelations(): array 
    { 
        return [
            RelationManagers\ActivitiesRelationManager::class
        ]; 
    }
}