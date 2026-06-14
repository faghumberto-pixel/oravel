<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use App\Models\User;
use App\Models\AssetCategory;
use App\Models\MeasurementUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'GESTÃO DE ATIVOS';
    
    protected static ?string $navigationLabel = 'Ativos / Equipamentos';
    protected static ?string $modelLabel = 'Ativo';
    protected static ?string $pluralModelLabel = 'Ativos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Ficha do Ativo')->tabs([
                
                // --- ABA 1: IDENTIFICAÇÃO (Com novos campos operacionais) ---
                Forms\Components\Tabs\Tab::make('Identificação')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Ativo')
                            ->required()->maxLength(255),
                        Forms\Components\TextInput::make('patrimonio')
                            ->label('Nº Patrimônio / Prefixo')
                            ->required()->maxLength(255),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('asset_category_id')
                            ->label('Categoria do Ativo')
                            ->relationship('assetCategory', 'name', fn (Builder $query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                            ->required()->searchable()->preload(),
                            
                        Forms\Components\Select::make('measurement_unit_id')
                            ->label('Unidade de Medida')
                            ->relationship('measurementUnit', 'name')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status Operacional')
                            ->options([
                                'disponivel' => 'Disponível',
                                'locado' => 'Locado',
                                'manutencao' => 'Em Manutenção',
                                'operando' => 'Em Operação'
                            ])->default('disponivel')->required(),
                    ]),

                    // --- NOVOS CAMPOS DE INTELLIGÊNCIA OPERACIONAL ---
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('criticidade_peso')
                            ->label('Nível de Criticidade')
                            ->options([
                                1 => '1 - Baixa',
                                2 => '2 - Média',
                                3 => '3 - Alta',
                                4 => '4 - Muito Alta',
                                5 => '5 - Crítica',
                            ])->default(1)->required(),
                        Forms\Components\TextInput::make('horimetro_atual')
                            ->label('Horímetro Atual')->numeric()->default(0),
                        Forms\Components\TextInput::make('odometro_atual')
                            ->label('Odômetro Atual')->numeric()->default(0),
                    ]),

                    Forms\Components\Textarea::make('description')
                        ->label('Observações Adicionais / Ficha Técnica')->rows(3),
                ]),

                // --- ABA 2: FINANCEIRO ---
                Forms\Components\Tabs\Tab::make('Financeiro & ROI')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('purchase_price')->label('Custo de Aquisição')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('residual_value')->label('Valor Residual')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('useful_life_months')->label('Vida Útil (Meses)')->numeric(),
                    ]),
                ]),

                // --- ABA 3: FOTO ---
                Forms\Components\Tabs\Tab::make('Foto')->schema([
                    Forms\Components\FileUpload::make('image_path')->image()->directory('assets-photos'),
                ]),

                // --- ABA 4: HISTÓRICO ---
                Forms\Components\Tabs\Tab::make('Histórico de Trabalho')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\Repeater::make('maintenanceOrders')
                            ->relationship('maintenanceOrders')
                            ->label('Ordens de Serviço')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('os_number')->label('Nº OS')->disabled(),
                                    Forms\Components\TextInput::make('status')->label('Situação')->disabled(),
                                    Forms\Components\TextInput::make('total_order_cost')->label('Custo')->prefix('R$')->disabled(),
                                ])
                            ])->disableItemCreation()->disableItemDeletion(),
                ]),
            ])->columnSpanFull()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('Foto')->circular(),
                Tables\Columns\TextColumn::make('patrimonio')->label('Patrimônio')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ativo')->searchable()->sortable(),
                // Badge de criticidade adicionado para gestão visual
                Tables\Columns\TextColumn::make('criticidade_peso')
                    ->label('Prioridade')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        5 => 'danger',
                        4 => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('horimetro_atual')->label('Horímetro'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('printQr')
                    ->label('Etiqueta')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn ($record) => route('asset.print-qr', [
                        'tenant' => \App\Support\Tenancy::current()?->slug ?? $record->tenant_id,
                        'asset'  => $record->id
                    ]))->openUrlInNewTab(),
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