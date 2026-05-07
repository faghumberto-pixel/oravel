<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use App\Models\CriticalityLevel;
use App\Models\ChecklistGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'GESTAO DE ATIVOS';
    protected static ?string $modelLabel = 'Ativo';
    protected static ?string $pluralModelLabel = 'Ativos';
    protected static ?string $navigationLabel = 'Ativos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Cadastro de Ativo')
                ->columnSpanFull()
                ->tabs([
                    // 1. IDENTIFICAÇÃO
                    Forms\Components\Tabs\Tab::make('1. Identificação')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('name')->label('Nome do Ativo')->required(),
                                Forms\Components\TextInput::make('patrimonio')->label('Patrimônio')->required(),
                                Forms\Components\Select::make('criticality_level_id')
                                    ->label('Criticidade')
                                    ->relationship('criticalityLevel', 'name', fn(Builder $query) => $query->where('tenant_id', Auth::user()->tenant_id))
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('code')->label('Código')->required(),
                                        Forms\Components\TextInput::make('name')->label('Descrição')->required(),
                                        Forms\Components\ColorPicker::make('color')->label('Cor'),
                                    ])
                                    ->createOptionUsing(fn($data) => CriticalityLevel::create(array_merge($data, ['tenant_id' => Auth::user()->tenant_id]))->id),
                            ]),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status Operacional')
                                    ->options(['disponivel' => 'Disponível', 'alocado' => 'Alocado', 'manutencao' => 'Em Manutenção'])
                                    ->default('disponivel')->required(),
                                Forms\Components\Select::make('checklist_group_id')
                                    ->label('Grupo de Checklist')
                                    ->relationship('checklistGroup', 'name', fn(Builder $query) => $query->where('tenant_id', Auth::user()->tenant_id))
                                    ->required(),
                                Forms\Components\TextInput::make('tag')->label('TAG / Código Interno'),
                            ]),
                        ]),

                    // 2. DADOS TÉCNICOS E CAPACIDADE
                    Forms\Components\Tabs\Tab::make('2. Dados Técnicos')
                        ->icon('heroicon-o-wrench')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('capacity_value')->label('Valor da Capacidade'),
                                Forms\Components\Select::make('capacity_unit')
                                    ->label('Unidade de Medida')
                                    ->options([
                                        'Potência' => ['kVA' => 'kVA', 'kW' => 'kW', 'cv' => 'cv', 'hp' => 'hp'],
                                        'Vazão/Pressão' => ['PCM' => 'PCM', 'PSI' => 'PSI', 'm3/h' => 'm³/h'],
                                        'Carga/Alcance' => ['tm' => 'tm', 't' => 'Toneladas', 'kg' => 'kg', 'm' => 'Metros'],
                                    ]),
                                Forms\Components\TextInput::make('manufacturing_year')->label('Ano de Fabricação')->numeric(),
                            ]),
                            Forms\Components\RichEditor::make('specification')->label('Especificações Detalhadas'),
                        ]),

                    // 3. FINANCEIRO E ROI (LCC)
                    Forms\Components\Tabs\Tab::make('3. Financeiro e ROI')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\DatePicker::make('acquisition_date')->label('Data de Aquisição'),
                                Forms\Components\TextInput::make('acquisition_value')->label('Valor de Compra')->numeric()->prefix('R$'),
                                Forms\Components\TextInput::make('residual_value')->label('Valor Residual (Venda)')->numeric()->prefix('R$'),
                                Forms\Components\TextInput::make('useful_life_years')->label('Vida Útil (Anos)')->numeric(),
                            ]),
                            Forms\Components\Section::make('Análise de ROI / LCC')
                                ->schema([
                                    Forms\Components\Placeholder::make('roi_display')
                                        ->label('ROI de Manutenção')
                                        ->content(fn ($record) => $record ? number_format($record->maintenance_roi, 2) . '%' : '0%'),
                                    Forms\Components\Placeholder::make('lcc_status')
                                        ->label('Status de Ciclo de Vida')
                                        ->content(fn ($record) => $record ? $record->lcc_analysis : 'Aguardando dados'),
                                ])->columns(2),
                        ]),

                    // 4. LOCALIZAÇÃO
                    Forms\Components\Tabs\Tab::make('4. Localização')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('cep')
                                    ->label('CEP da Base')
                                    ->suffixAction(
                                        Forms\Components\Actions\Action::make('geo')
                                            ->icon('heroicon-m-magnifying-glass')
                                            ->action(function ($state, Forms\Set $set) {
                                                if (!$state) return;
                                                $res = Http::get("https://viacep.com.br/ws/{$state}/json/")->json();
                                                if (!isset($res['erro'])) {
                                                    $geo = Http::get("https://nominatim.openstreetmap.org/search?q=" . urlencode("{$res['logradouro']}, {$res['localidade']}") . "&format=json&limit=1")->json();
                                                    if ($geo) { $set('latitude', $geo[0]['lat']); $set('longitude', $geo[0]['lon']); }
                                                }
                                            })
                                    ),
                                Forms\Components\TextInput::make('latitude')->label('Lat')->readonly(),
                                Forms\Components\TextInput::make('longitude')->label('Long')->readonly(),
                            ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('patrimonio')->label('Patrimônio')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Ativo')->searchable(),
                Tables\Columns\TextColumn::make('criticalityLevel.name')
                    ->label('Criticidade')
                    ->badge()
                    ->color(fn ($record) => $record->criticalityLevel->color ?? 'gray'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            ]);
    }

    public static function getPages(): array {
        return ['index' => Pages\ListAssets::route('/'), 'create' => Pages\CreateAsset::route('/create'), 'edit' => Pages\EditAsset::route('/{record}/edit')];
    }
}
