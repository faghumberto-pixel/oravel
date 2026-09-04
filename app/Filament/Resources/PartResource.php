<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartResource\Pages;
use App\Models\Part;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartResource extends BaseResource
{
    protected static ?string $model = Part::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 11;

    protected static ?string $label = 'Peça/Insumo';

    protected static ?string $pluralLabel = 'Peças e Insumos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->schema([
                    Forms\Components\TextInput::make('sku')
                        ->label('SKU (Código Interno)')
                        ->placeholder('FLT-001-OLE')
                        ->required()
                        ->maxLength(100)
                        ->unique(Part::class, 'sku', ignoreRecord: true),

                    Forms\Components\TextInput::make('barcode')
                        ->label('Código de Barras (EAN/UPC)')
                        ->placeholder('7891234567890')
                        ->maxLength(50),

                    Forms\Components\SelectRelation::make('part_category_id')
                        ->relationship('category', 'name')
                        ->label('Categoria')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                        ]),
                ])
                ->columns(3),

            Forms\Components\Section::make('Descrição e Especificações')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome/Descrição')
                        ->placeholder('Filtro de Óleo HF1234')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrição Detalhada')
                        ->placeholder('Informações adicionais sobre a peça...')
                        ->rows(3),

                    Forms\Components\Select::make('unit_of_measure')
                        ->label('Unidade de Medida')
                        ->options(Part::UNITS)
                        ->required()
                        ->searchable(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Custos e Estoque')
                ->schema([
                    Forms\Components\TextInput::make('cost_price')
                        ->label('Custo Médio Ponderado (R$)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->readOnly()
                        ->helperText('Calculado automaticamente a partir das entradas'),

                    Forms\Components\TextInput::make('minimum_stock')
                        ->label('Estoque Mínimo')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->default(0),

                    Forms\Components\TextInput::make('maximum_stock')
                        ->label('Estoque Máximo')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0),
                ])
                ->columns(3),

            Forms\Components\Section::make('Localização e Status')
                ->schema([
                    Forms\Components\TextInput::make('location_shelf')
                        ->label('Localização (Corredor/Prateleira/Gaveta)')
                        ->placeholder('Corredor B - Prateleira 3 - Gaveta 12')
                        ->maxLength(255),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Peça')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('Código de Barras')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('Unidade')
                    ->badge(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Custo (R$)')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Total em Estoque')
                    ->sortable()
                    ->state(fn (Part $record) => $record->total_stock),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status de Estoque')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'excess' => 'info',
                        'normal' => 'success',
                    })
                    ->state(fn (Part $record) => $record->stock_status),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('part_category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('unit_of_measure')
                    ->label('Unidade de Medida')
                    ->options(Part::UNITS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Todos')
                    ->trueLabel('Ativos')
                    ->falseLabel('Inativos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('viewStocks')
                    ->label('Ver Estoque')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Part $record) => WarehouseStockResource::getUrl('index', ['parts' => $record->id])),
                Tables\Actions\Action::make('viewMovements')
                    ->label('Histórico')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Part $record) => StockMovementResource::getUrl('index', ['parts' => $record->id])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sku', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParts::route('/'),
            'create' => Pages\CreatePart::route('/create'),
            'view' => Pages\ViewPart::route('/{record}'),
            'edit' => Pages\EditPart::route('/{record}/edit'),
        ];
    }
}
