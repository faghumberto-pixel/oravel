<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Supplier;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

#[BelongsToFeature('materials')]
class MaterialResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Material::class;

    protected static ?string $modelLabel = 'Material/Peças';

    protected static ?string $pluralModelLabel = 'Materiais/Peças';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    // Alterado para 'Suprimentos' (Capitalizado) para garantir consistência
    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Básicas')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU / Código')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('part_number')
                            ->label('Part Number (Fabricante)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de Barras / EAN')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Material')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('material_category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name', modifyQueryUsing: function (Builder $query) {
                                return $query->where('tenant_id', Tenancy::current()?->id);
                            })
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome da Categoria')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->label('Descrição')
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['tenant_id'] = Tenancy::current()?->id;
                                $record = MaterialCategory::create($data);

                                return $record->id;
                            }),

                        Forms\Components\TextInput::make('ncm')
                            ->label('NCM')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Compatibilidade')
                    ->schema([
                        Forms\Components\Select::make('checklistGroups')
                            ->label('Grupos de Ativo Compatíveis')
                            ->relationship('checklistGroups', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),

                Forms\Components\Section::make('Inteligência Comercial e Suprimentos')
                    ->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Fornecedor Homologado')
                            ->relationship('supplier', 'name', modifyQueryUsing: function (Builder $query) {
                                return $query->where('tenant_id', Tenancy::current()?->id);
                            })
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Razão Social / Nome')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('document')
                                    ->label('CNPJ / CPF')
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['tenant_id'] = Tenancy::current()?->id;
                                $record = Supplier::create($data);

                                return $record->id;
                            }),

                        Forms\Components\TextInput::make('brand_name')
                            ->label('Marca do Componente / Equipamento')
                            ->maxLength(255),

                        Forms\Components\Select::make('supplier_type')
                            ->label('Tipo de Canal de Fornecimento')
                            ->options([
                                'fabricante' => 'Fabricante Direto',
                                'distribuidor' => 'Distribuidor Autorizado',
                                'varejo' => 'Revenda / Varejo Local',
                                'importacao' => 'Importador',
                            ])
                            ->native(false),
                    ])->columns(3),

                Forms\Components\Section::make('Controle Financeiro e Estoque')
                    ->schema([
                        Forms\Components\TextInput::make('unit_cost')->label('Custo Unitário')->required()->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('last_purchase_price')->label('Último Preço de Compra')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('price')->label('Preço de Venda')->required()->numeric()->default(0)->prefix('R$'),
                        Forms\Components\TextInput::make('current_stock')
                            ->label('Estoque Atual (total, todas as filiais)')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Somado automaticamente. Editável por filial na aba "Estoque por Filial".'),
                        Forms\Components\TextInput::make('min_stock')->label('Estoque Mínimo (padrão)')->required()->numeric()->default(0)
                            ->helperText('Usado como padrão ao criar uma filial nova pra este material.'),
                        Forms\Components\TextInput::make('max_stock')->label('Estoque Máximo (padrão)')->required()->numeric()->default(0),
                        Forms\Components\Select::make('unit_of_measure')
                            ->label('Unidade de Medida')
                            ->options(['un' => 'Unidade', 'l' => 'Litro', 'kg' => 'Quilograma', 'm' => 'Metro', 'par' => 'Par', 'cx' => 'Caixa'])
                            ->default('un')
                            ->native(false),
                        Forms\Components\TextInput::make('warehouse_location')->label('Localização no Almoxarifado')->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Rastreabilidade')
                    ->schema([
                        Forms\Components\Toggle::make('requires_serial_number')
                            ->label('Exige número de série ao aplicar')
                            ->helperText('Técnico será obrigado a informar o nº de série antes de salvar o uso desta peça.'),
                        Forms\Components\Toggle::make('is_remanufactured')
                            ->label('Peça remanufaturada'),
                        Forms\Components\TextInput::make('warranty_days')
                            ->label('Garantia (dias)')
                            ->numeric()
                            ->helperText('Se a peça falhar dentro deste prazo em outra OS, o gestor é alertado.'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Categoria')->sortable(),
                Tables\Columns\TextColumn::make('brand_name')->label('Marca')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Fornecedor')->sortable(),
                Tables\Columns\TextColumn::make('price')->label('Preço')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Qtd. Atual')
                    ->numeric()
                    ->color(fn ($record) => $record->current_stock <= $record->min_stock ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->relationship('category', 'name')->label('Categoria'),
                Tables\Filters\SelectFilter::make('supplier')->relationship('supplier', 'name')->label('Fornecedor'),
                Tables\Filters\Filter::make('estoque_baixo')
                    ->label('Abaixo do Estoque Mínimo')
                    ->query(fn ($query) => $query->whereColumn('current_stock', '<', 'min_stock'))
                    ->toggle(),
                Tables\Filters\TernaryFilter::make('supplier_id')
                    ->label('Fornecedor Homologado')
                    ->nullable()
                    ->trueLabel('Com fornecedor')
                    ->falseLabel('Sem fornecedor')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('supplier_id'),
                        false: fn ($query) => $query->whereNull('supplier_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LocationStocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
