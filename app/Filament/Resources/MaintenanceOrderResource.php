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
    protected static ?string $pluralModelLabel = 'Ordens de Serviço';
    protected static ?string $modelLabel = 'Ordem de Serviço';
    protected static ?string $tenantRelationshipName = 'maintenanceOrders';
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function getRecordRouteKeyName(): ?string { return 'id'; }

    // --- SEGURANÇA FORÇADA ---
    public static function canViewAny(): bool { 
        return auth()->user()->isAdmin() || auth()->user()->can('ler_ordem_servico'); 
    }
    public static function canEdit($record): bool { 
        return auth()->user()->isAdmin() || auth()->user()->can('editar_ordem_servico'); 
    }
    public static function canView($record): bool { 
        return auth()->user()->isAdmin() || auth()->user()->can('ler_ordem_servico'); 
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $tenantId = Filament::getTenant()?->id;
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->where('tenant_id', $tenantId);

        if ($user && !(method_exists($user, 'isAdmin') && $user->isAdmin())) {
            $query->where('technician_id', $user->id);
        }
        return $query;
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
                            ->required()->searchable()->preload()->live()
                            ->getSearchResultsUsing(function (string $search) {
                                $tenantId = Filament::getTenant()?->id;
                                if (!$tenantId) return [];
                                return Asset::where('tenant_id', $tenantId)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")->orWhere('patrimonio', 'like', "%{$search}%");
                                    })->limit(50)->get()->mapWithKeys(fn ($asset) => [$asset->id => "{$asset->name} [Pat: {$asset->patrimonio}]"]);
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $asset = Asset::find($state);
                                    if ($asset) {
                                        $set('checklists', Asset::getDefaultChecklist($asset?->asset_category ?? 'default'));
                                        $set('horimetro_anterior', $asset->last_horimetro ?? 0);
                                    }
                                }
                            })->prefixIcon('heroicon-m-qr-code'),

                        Forms\Components\Select::make('maintenance_type')
                            ->label('Tipo de Operação')
                            ->options(['Check-in' => 'Check-in (Mobilização)', 'Check-out' => 'Check-out (Desmobilização)', 'Preventiva' => 'Manutenção Preventiva', 'Corretiva' => 'Manutenção Corretiva'])
                            ->required()->native(false)->live(),
                    ]),
                    
                    Forms\Components\TextInput::make('service_type')
                        ->label('Natureza do Serviço')
                        ->disabled()
                        ->dehydrated(true)
                        ->helperText('Definido automaticamente pelo sistema (Interno/Externo).'),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('horimetro_anterior')->label('Hor. Anterior')->numeric()->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('horimetro_entry')->label('Horímetro Atual')->numeric()->default(0)->required()->prefixIcon('heroicon-m-clock'),
                        Forms\Components\Select::make('fuel_level')->label('Nível Combustível')->options(['0'=>'Reserva', '25'=>'1/4', '50'=>'1/2', '75'=>'3/4', '100'=>'Cheio'])->native(false),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('technician_id')->label('Responsável Técnico')->options(fn() => User::where('tenant_id', Filament::getTenant()?->id)->pluck('name', 'id'))->required()->searchable(),
                        Forms\Components\Select::make('client_id')->label('Cliente / Localidade')->relationship('client', 'name', fn(Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id))->searchable(),
                    ]),
                    Forms\Components\Select::make('status')->options(['Aberto' => 'Aberto', 'Pendente' => 'Pendente', 'Em Andamento' => 'Em Andamento', 'Concluída' => 'Concluída', 'Cancelada' => 'Cancelada'])->default('Aberto')->disabled()
                ]),
                Forms\Components\Tabs\Tab::make('Checklist / Dossiê')->schema([
                    Forms\Components\Repeater::make('checklists')
                        ->relationship('checklists')
                        ->schema([
                            Forms\Components\TextInput::make('item')->label('Item de Verificação')->readOnly()->columnSpan(2),
                            Forms\Components\Toggle::make('status')->label('OK')->onColor('success')->default(true),
                            Forms\Components\TextInput::make('observation')->label('Obs / Avaria'),
                        ])->addable(false)->deletable(false)->columns(4),
                ]),
                Forms\Components\Tabs\Tab::make('Materiais')->schema([
                    Forms\Components\Repeater::make('materials')
                        ->relationship('materials')
                        ->schema([
                            Forms\Components\Select::make('material_id')->relationship('material', 'name', fn(Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id))->required()->searchable()->live(),
                            Forms\Components\TextInput::make('quantity')->label('Qtd')->numeric()->default(1),
                            Forms\Components\TextInput::make('unit_price')->label('Vlr Unit')->prefix('R$')->readOnly(),
                        ])->columns(3),
                ]),
                Forms\Components\Tabs\Tab::make('Apontamentos')->schema([
                    Forms\Components\Textarea::make('technical_notes')
                        ->label('Laudo Técnico / Observações')
                        ->rows(5)
                        ->columnSpanFull()
                        ->hint(new HtmlString('
                            <button type="button" onclick="startDictation(this)" class="flex items-center gap-1 text-xs font-bold text-primary-600 hover:text-primary-800">
                                🎤 GRAVAR VOZ
                            </button>
                            <script>
                                function startDictation(btn) {
                                    if (!window.webkitSpeechRecognition) { alert("Navegador não suporta voz."); return; }
                                    const recognition = new webkitSpeechRecognition();
                                    recognition.lang = "pt-BR";
                                    btn.innerText = "Ouvindo...";
                                    recognition.onresult = (e) => {
                                        const t = btn.closest(".filament-forms-component-container").querySelector("textarea");
                                        t.value += (t.value ? " " : "") + e.results[0][0].transcript;
                                        t.dispatchEvent(new Event("input"));
                                        btn.innerText = "GRAVAR VOZ";
                                    };
                                    recognition.start();
                                }
                            </script>
                        ')),
                    Forms\Components\SpatieMediaLibraryFileUpload::make('evidences')
                        ->label('Fotos')
                        ->collection('os_evidences')
                        ->image()
                        ->conversion('thumb')
                        ->maxSize(2048)
                        ->multiple()
                        ->reorderable()
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(2)->schema([
                        SignaturePad::make('technician_signature')->label('Assinatura Técnico'),
                        SignaturePad::make('client_signature')->label('Assinatura Cliente'),
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
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y'),
                Tables\Columns\TextColumn::make('os_number')->label('Nº OS')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable(),
                Tables\Columns\TextColumn::make('service_type')->label('Natureza')->badge()->color(fn (string $state): string => $state === 'Externo' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente'),
            ])
            ->actions([
                Tables\Actions\Action::make('print_os')
                    ->label('Imprimir OS')
                    ->color('gray')
                    ->icon('heroicon-o-printer')
                    ->url(fn (MaintenanceOrder $record): string => route('maintenance-orders.print', $record->id))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array { return ['index' => Pages\ListMaintenanceOrders::route('/'), 'create' => Pages\CreateMaintenanceOrder::route('/create'), 'edit' => Pages\EditMaintenanceOrder::route('/{record}/edit')]; }
}