<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\MaintenanceOrderResource\Pages;
use App\Forms\Components\CameraCapture;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Support\FormHelpers;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

#[BelongsToFeature('maintenance')]
class MaintenanceOrderResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = MaintenanceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Ordens de Serviço';

    protected static ?string $pluralModelLabel = 'Ordens de Serviço';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->can('update', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Fluxo Oravel')->tabs([

                // --- ABA 1: DADOS GERAIS ---
                Forms\Components\Tabs\Tab::make('Dados Gerais')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('asset_id')
                            ->label('Ativo / QR Code')
                            ->placeholder('Bipe o código ou digite Pat/Série/Tag')
                            ->required()->searchable()->preload()->live()
                            ->getSearchResultsUsing(function (string $search) {
                                $tenantId = Tenancy::current()?->id;
                                if (! $tenantId) {
                                    return [];
                                }

                                return Asset::where('tenant_id', $tenantId)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")->orWhere('patrimonio', 'like', "%{$search}%");
                                    })->limit(50)->get()->mapWithKeys(fn ($asset) => [$asset->id => "{$asset->name} [Pat: {$asset->patrimonio}]"]);
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $asset = Asset::find($state);
                                    if ($asset) {
                                        $set('horimetro_anterior', $asset->last_horimetro ?? 0);
                                    }
                                }
                            })->prefixIcon('heroicon-m-qr-code'),
                        Forms\Components\Select::make('maintenance_type')
                            ->label('Tipo de Operação')
                            ->options(['Check-in' => 'Check-in (Mobilização)', 'Check-out' => 'Check-out (Desmobilização)', 'Preventiva' => 'Manutenção Preventiva', 'Corretiva' => 'Manutenção Corretiva'])
                            ->required()->native(false)->live(),
                    ]),

                    Forms\Components\Placeholder::make('grupo_display')
                        ->label('Grupo do Ativo')
                        ->content(function (Get $get) {
                            $asset = Asset::find($get('asset_id'));

                            return $asset?->checklistGroup?->name ?? 'Sem grupo definido';
                        }),

                    Forms\Components\Placeholder::make('preventivas_pendentes')
                        ->label('Preventivas Sugeridas (por Horímetro)')
                        ->visible(fn (Get $get) => (bool) $get('asset_id'))
                        ->content(function (Get $get) {
                            $asset = Asset::find($get('asset_id'));

                            if (! $asset || ! $asset->checklist_group_id) {
                                return 'Ativo sem grupo definido — sem template de preventiva.';
                            }

                            $plans = MaintenancePlan::where('checklist_group_id', $asset->checklist_group_id)
                                ->where('is_active', true)
                                ->get();

                            if ($plans->isEmpty()) {
                                return 'Nenhum item de preventiva cadastrado para este grupo.';
                            }

                            $lines = $plans->map(function ($plan) use ($asset) {
                                $status = $plan->dueStatusForAsset($asset);
                                $situacao = $status['is_overdue']
                                    ? 'VENCIDO há '.number_format($status['overdue_hours'], 0).'h'
                                    : 'Próxima em '.number_format($status['due_at_hours'] - (float) $asset->horimetro_atual, 0).'h';

                                return "{$plan->name}: trocado em {$status['last_service_hours']}h, horímetro atual {$asset->horimetro_atual}h, intervalo {$plan->interval_hours}h → {$situacao}";
                            });

                            return new HtmlString(
                                $lines->map(fn ($line) => '<div>'.e($line).'</div>')->implode('')
                            );
                        }),

                    // criticality_level_id (rotulado "Matriz ABC" mas era outra coisa --
                    // tabela criticality_levels solta, sem tela de cadastro, e o unico
                    // grafico que tentava agregar isso tinha um bug de comparacao de
                    // tipo). A Matriz ABC de verdade vive em Asset::abcMatrix(), que ja
                    // tem cadastro proprio (AbcMatrixResource) -- aqui so exibimos ela.
                    Forms\Components\Placeholder::make('matriz_abc_ativo')
                        ->label('Matriz ABC do Ativo')
                        ->content(function (Get $get) {
                            $asset = $get('asset_id') ? Asset::find($get('asset_id')) : null;
                            $nivel = $asset?->abcMatrix?->nivel;

                            return $nivel
                                ? "Nível {$nivel}"
                                : new HtmlString('<span class="text-gray-400">Ativo sem Matriz ABC cadastrada.</span>');
                        }),

                    Forms\Components\TextInput::make('service_type')->label('Natureza do Serviço')->disabled()->dehydrated(true),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('horimetro_anterior')->label('Hor. Anterior')->numeric()->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('horimetro_entry')->label('Horímetro Atual')->numeric()->default(0)->required()->prefixIcon('heroicon-m-clock'),
                        Forms\Components\Select::make('fuel_level')->label('Nível Combustível')->options(['0' => 'Reserva', '25' => '1/4', '50' => '1/2', '75' => '3/4', '100' => 'Cheio'])->native(false),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('technician_id')->label('Responsável Técnico')->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))->required()->searchable(),
                        Forms\Components\Select::make('client_id')->label('Cliente')->relationship('client', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))->searchable(),
                        Forms\Components\DateTimePicker::make('scheduled_at')->label('Agendado para')->helperText('Aparece na Agenda Técnica.'),
                    ]),
                    Forms\Components\Select::make('status')
                        ->label('Status da OS')->options(['Aberto' => 'Aberto', 'Pendente' => 'Pendente', 'Em Andamento' => 'Em Andamento', 'Concluída' => 'Concluída', 'Cancelada' => 'Cancelada'])
                        ->default('Aberto')->disabled()->dehydrated(true),
                ]),

                // --- ABA 2: APONTAMENTOS ---
                Forms\Components\Tabs\Tab::make('Apontamentos')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DateTimePicker::make('started_at')->label('Início do Atendimento')->disabled()->dehydrated(true),
                        Forms\Components\DateTimePicker::make('finished_at')->label('Fim do Atendimento')->disabled()->dehydrated(true),
                    ]),
                    Forms\Components\Textarea::make('description')->label('Problema Relatado / Escopo do Serviço')->rows(3)->required()->hint(FormHelpers::voiceButton()),
                    Forms\Components\Textarea::make('technical_notes')->label('Notas Técnicas / Diagnóstico Executado')->rows(3)->hint(FormHelpers::voiceButton()),
                ]),

                // --- ABA 3: VISTORIA / CHECKLIST ---
                Forms\Components\Tabs\Tab::make('Vistoria / Checklist')
                    ->schema([
                        Forms\Components\Repeater::make('checklists')
                            ->relationship('checklists')
                            ->label('Checklist do Ativo (básico do Grupo + itens extras)')
                            ->schema([
                                Forms\Components\TextInput::make('item_name')->label('Item de Inspeção')->disabled()->dehydrated(true),
                                Forms\Components\ToggleButtons::make('status')
                                    ->label('Conformidade')
                                    ->options(['conforme' => 'Conforme', 'nao_conforme' => 'Não Conforme', 'nao_aplicavel' => 'N/A'])
                                    ->colors(['conforme' => 'success', 'nao_conforme' => 'danger', 'nao_aplicavel' => 'gray'])
                                    ->inline(),
                                Forms\Components\TextInput::make('notes')->label('Observações / Evidência'),
                                Forms\Components\SpatieMediaLibraryFileUpload::make('photos')
                                    ->collection('photos')
                                    ->label('Foto')
                                    ->image(),
                            ])->columns(3)->disableItemCreation()->disableItemDeletion(),
                    ]),

                // --- ABA 4: FOTOS ---
                Forms\Components\Tabs\Tab::make('Fotos e Evidências')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        CameraCapture::make('photo_before')
                            ->label('Foto ANTES do Serviço (Estado Inicial)'),
                        CameraCapture::make('photo_after')
                            ->label('Foto DEPOIS do Serviço (Resultado Final)'),
                    ]),

                    Forms\Components\Repeater::make('extra_evidences')
                        ->label('Evidências Adicionais')
                        ->dehydrated()
                        ->addActionLabel('Adicionar Evidência')
                        ->schema([
                            CameraCapture::make('photo')
                                ->label('Foto'),
                            Forms\Components\TextInput::make('category')
                                ->label('Categoria')
                                ->datalist(['Painel/Horímetro', 'Estrutura Geral', 'Avaria'])
                                ->placeholder('Ex: Painel/Horímetro, Avaria: Esteira Esquerda'),
                            Forms\Components\ToggleButtons::make('severity')
                                ->label('Severidade')
                                ->options(['ok' => 'OK', 'avaria' => 'Avaria'])
                                ->colors(['ok' => 'success', 'avaria' => 'danger'])
                                ->default('ok')->inline(),
                            Forms\Components\Textarea::make('observation')
                                ->label('Observação')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

                // --- ABA 5: MATERIAIS ---
                Forms\Components\Tabs\Tab::make('Materiais')->schema([
                    Forms\Components\Repeater::make('materials')
                        ->relationship('materials')
                        ->schema([
                            Forms\Components\Select::make('material_id')->relationship('material', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))->required()->searchable(),
                            Forms\Components\TextInput::make('quantity')->label('Qtd')->numeric()->default(1)->required(),
                        ])->columns(2),
                ]),

                // --- ABA 5B: CUSTOS ---
                // Colunas reais (labor_cost/material_cost/logistics_cost/
                // total_order_cost), mas ate agora sem nenhum campo em
                // nenhuma aba da O.S. -- so existia leitura disso no
                // dossie do Ativo. total_order_cost nao e calculado
                // automaticamente em nenhum lugar do app, entao os 4
                // ficam editaveis manualmente, igual as outras colunas.
                Forms\Components\Tabs\Tab::make('Custos')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('labor_cost')
                            ->label('Mão de Obra (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('material_cost')
                            ->label('Material (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('logistics_cost')
                            ->label('Logística (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('total_order_cost')
                            ->label('Custo Total (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                    ]),
                ]),

                // --- ABA 6: ASSINATURAS ---
                Forms\Components\Tabs\Tab::make('Assinaturas Digitais')->schema([
                    Forms\Components\Placeholder::make('info_sig')->content('Colete a assinatura na tela do dispositivo.'),
                    Forms\Components\Grid::make(2)->schema([
                        SignaturePad::make('technician_signature')
                            ->label('Assinatura do Técnico')
                            ->loadStrategy('idle'),
                        SignaturePad::make('client_signature')
                            ->label('Assinatura do Cliente')
                            ->loadStrategy('idle'),
                    ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->poll('10s')->columns([
            static::tenantColumn(),
            Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y'),
            Tables\Columns\TextColumn::make('os_number')->label('Nº OS')->searchable()->weight('bold'),
            Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable(),
            Tables\Columns\TextColumn::make('asset.assetCategory.name')
                ->label('Categoria do Ativo')
                ->sortable()
                ->placeholder('Sem Categoria'),
            Tables\Columns\TextColumn::make('asset.checklistGroup.name')
                ->label('Grupo')
                ->badge()
                ->color('gray')
                ->placeholder('Sem grupo'),
            Tables\Columns\TextColumn::make('criticalityLevel.name')
                ->label('Matriz ABC')
                ->badge()
                ->color(fn ($record): string => match (strtolower($record->criticalityLevel?->code ?? '')) {
                    'c' => 'danger',
                    'b' => 'warning',
                    default => 'gray',
                })
                ->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')
                ->label('Status')
                ->multiple()
                ->options([
                    'Aberto' => 'Aberto',
                    'Pendente' => 'Pendente',
                    'Em Andamento' => 'Em Andamento',
                    'Concluída' => 'Concluída',
                    'Cancelada' => 'Cancelada',
                ]),
            Tables\Filters\SelectFilter::make('maintenance_type')
                ->label('Tipo')
                ->options([
                    'Corretiva' => 'Corretiva',
                    'Preventiva' => 'Preventiva',
                ]),
            Tables\Filters\SelectFilter::make('matriz_abc')
                ->label('Matriz ABC')
                ->options(['A' => 'A', 'B' => 'B', 'C' => 'C'])
                ->query(fn (Builder $query, array $data) => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $q, $nivel) => $q->whereHas('asset.abcMatrix', fn (Builder $q2) => $q2->where('nivel', $nivel))
                )),
            Tables\Filters\SelectFilter::make('asset.checklist_group_id')
                ->label('Grupo')
                ->relationship('asset.checklistGroup', 'name'),
            Tables\Filters\SelectFilter::make('technician_id')
                ->label('Técnico')
                ->relationship('technician', 'name'),
            Tables\Filters\Filter::make('atrasada')
                ->label('Em atraso (+3 dias aberta)')
                ->toggle()
                ->query(fn (Builder $query) => $query
                    ->whereIn('status', ['Aberto', 'Pendente', 'Em Andamento'])
                    ->where('created_at', '<', now()->subDays(3))),

        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    // GATILHO DE NOTIFICAÇÃO
    public static function afterCreate($record): void
    {
        Notification::make()
            ->title('Nova O.S. Gerada')
            ->body("A O.S. #{$record->id} foi criada para o ativo {$record->asset?->name}.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->info()
            ->sendToDatabase(auth()->user());
    }

    public static function getWidgets(): array
    {
        return [];
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
