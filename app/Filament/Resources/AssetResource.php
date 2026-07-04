<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use App\Support\Tenancy;
use Filament\Forms\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $modelLabel = 'Ativo';
    protected static ?string $pluralModelLabel = 'Ativos';
    protected static ?string $navigationGroup = 'GESTÃO DE ATIVOS';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function canViewAny(): bool { return true; }
    public static function canCreate(): bool { return true; }
    public static function canEdit($record): bool { return true; }
    public static function canDelete($record): bool { return true; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Tenancy::current()?->id);
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
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('asset_category')
                                    ->label('Categoria e Tipo')
                                    ->options(\App\Models\AssetCategory::pluck('name', 'name'))
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->live() 
                                    ->afterStateUpdated(fn ($state, callable $set) => 
                                        $set('checklist', Asset::getDefaultChecklist($state))
                                    ),

                                Forms\Components\TextInput::make('name')
                                    ->label('Nome/Modelo')
                                    ->required(),
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
                                        ->content(function (\Filament\Forms\Get $get) {
                                            $acquisitionValue = (float) ($get('acquisition_value') ?? 0);
                                            $residualValue = (float) ($get('residual_value') ?? 0);
                                            $usefulLifeYears = (int) ($get('useful_life_years') ?? 0);
                                            $acquisitionDate = $get('acquisition_date');

                                            if ($acquisitionValue <= 0 || $usefulLifeYears <= 0 || !$acquisitionDate) {
                                                return new \Illuminate\Support\HtmlString('<span class="text-gray-400">Preencha os campos acima para calcular.</span>');
                                            }

                                            $depreciableAmount = max($acquisitionValue - $residualValue, 0);
                                            $monthlyDepreciation = $depreciableAmount / ($usefulLifeYears * 12);
                                            $monthsElapsed = \Carbon\Carbon::parse($acquisitionDate)->diffInMonths(now());
                                            $accumulated = min($monthlyDepreciation * $monthsElapsed, $depreciableAmount);
                                            $currentValue = $acquisitionValue - $accumulated;
                                            $percentage = $depreciableAmount > 0 ? round(($accumulated / $depreciableAmount) * 100, 1) : 0;

                                            return new \Illuminate\Support\HtmlString(
                                                "<div class='flex gap-6 text-sm'>" .
                                                "<div><span class='text-gray-400'>Valor Atual:</span> <b>R$ " . number_format($currentValue, 2, ',', '.') . "</b></div>" .
                                                "<div><span class='text-gray-400'>Depreciado:</span> <b>R$ " . number_format($accumulated, 2, ',', '.') . "</b></div>" .
                                                "<div><span class='text-gray-400'>Percentual:</span> <b>{$percentage}%</b></div>" .
                                                "</div>"
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
                                            ->prefixIcon('heroicon-m-flag'),

                                        Forms\Components\TextInput::make('last_horimetro')
                                            ->label('Leitura Atual (Sistema)')
                                            ->numeric()
                                            ->readOnly() // Permite gravação pelo sistema, mas protege contra digitação acidental
                                            ->helperText('Sincronizado automaticamente pelas Ordens de Serviço.')
                                            ->prefixIcon('heroicon-m-arrow-path'),

                                        Forms\Components\Select::make('status')
                                            ->label('Status Operacional')
                                            ->options([
                                                'disponivel' => 'Disponível',
                                                'locado'     => 'Locado',
                                                'manutencao' => 'Em Manutenção',
                                                'operando'   => 'Em Operação',
                                            ])
                                            ->default('disponivel')
                                            ->required(),
                                    ]),
                                ]),
                        ]),

                    // ABA 2: RASTREABILIDADE (QR CODE)
                    Tabs\Tab::make('Rastreabilidade e QR Code')
                        ->icon('heroicon-m-qr-code')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('patrimonio')
                                    ->label('Nº Patrimônio')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->prefixIcon('heroicon-m-hashtag'),

                                Forms\Components\TextInput::make('asset_tag')
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
                                    if (!$record) return "O QR Code será gerado após o primeiro salvamento.";
                                    $url = url("/admin/assets/{$record->id}");
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
                                ->itemLabel(fn (array $state): ?string => 
                                    isset($state['created_at']) 
                                    ? "Registro de " . \Carbon\Carbon::parse($state['created_at'])->format('d/m/Y H:i') 
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

                            Forms\Components\Placeholder::make('financial_summary')
                                ->label('Resumo Financeiro')
                                ->content(function ($record) {
                                    if (!$record) {
                                        return new \Illuminate\Support\HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
                                    }
                                    $s = $record->getFinancialSummary();
                                    $resultColor = $s['result'] >= 0 ? '#16a34a' : '#dc2626';
                                    $fmt = fn($v) => number_format($v, 2, ',', '.');
                                    return new \Illuminate\Support\HtmlString(
                                        "<div class='grid grid-cols-3 gap-4 text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-lg'>" .
                                        "<div><span class='text-gray-400'>Valor de Aquisição:</span><br><b>R\$ {$fmt($s['acquisition_value'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Valor Atual (Depreciado):</span><br><b>R\$ {$fmt($s['current_value'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Depreciação Acumulada:</span><br><b>R\$ {$fmt($s['accumulated_depreciation'])} ({$s['depreciation_percentage']}%)</b></div>" .
                                        "<div><span class='text-gray-400'>Custo Mão de Obra:</span><br><b>R\$ {$fmt($s['total_labor_cost'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Custo Material:</span><br><b>R\$ {$fmt($s['total_material_cost'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Custo Logística:</span><br><b>R\$ {$fmt($s['total_logistics_cost'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Custo Total Manutenção:</span><br><b>R\$ {$fmt($s['total_maintenance_cost'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Receita Total de Locações:</span><br><b>R\$ {$fmt($s['total_rental_revenue'])}</b></div>" .
                                        "<div><span class='text-gray-400'>Resultado:</span><br><b style='color: {$resultColor}'>R\$ {$fmt($s['result'])}</b></div>" .
                                        "</div>"
                                    );
                                })
                                ->columnSpanFull(),
                        ]),

                    // ABA 4: LOGS DE AUDITORIA (Rastreabilidade de Ações)
                    Tabs\Tab::make('Logs de Auditoria')
                        ->icon('heroicon-m-finger-print')
                        ->schema([
                            Forms\Components\Repeater::make('activities')
                                ->relationship('activities')
                                ->label('Histórico de Alterações no Cadastro')
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('description')
                                            ->label('Ação')
                                            ->formatStateUsing(fn ($state) => match($state) {
                                                'created' => 'Criação',
                                                'updated' => 'Atualização',
                                                'deleted' => 'Exclusão',
                                                default => $state
                                            })->disabled(),
                                        Forms\Components\TextInput::make('causer.name')->label('Usuário')->disabled(),
                                        Forms\Components\TextInput::make('created_at')->label('Data/Hora')->disabled(),
                                    ]),
                                    Forms\Components\KeyValue::make('properties.attributes')
                                        ->label('Valores Novos')
                                        ->disabled(),
                                    Forms\Components\KeyValue::make('properties.old')
                                        ->label('Valores Anteriores')
                                        ->disabled(),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
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
                Tables\Columns\TextColumn::make('patrimonio')
                    ->label('Patrimônio')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Asset $record): string => "S/N: " . ($record->serial_number ?? 'N/A')),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Equipamento')
                    ->searchable()
                    ->description(fn (Asset $record): string => "Tag: " . ($record->asset_tag ?? '---')),
                
                Tables\Columns\TextColumn::make('last_horimetro')
                    ->label('Horímetro Atual')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('asset_category')
                    ->label('Categoria')
                    ->badge(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disponivel' => 'success',
                        'manutencao' => 'danger',
                        'locado' => 'warning',
                        default => 'info',
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}