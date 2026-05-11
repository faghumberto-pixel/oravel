<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetCategoryResource\Pages;
use App\Models\AssetCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'GESTÃO DE ATIVOS';
    protected static ?string $modelLabel = 'Categoria de Ativo';
    protected static ?string $pluralModelLabel = 'Categorias';
    protected static ?int $navigationSort = 1; // Aparece antes dos ativos no menu

    /**
     * PERMISSÕES: Ativa as ações de Criar, Editar e Excluir no painel
     */
    public static function canViewAny(): bool { return true; }
    public static function canCreate(): bool { return true; }
    public static function canEdit($record): bool { return true; }
    public static function canDelete($record): bool { return true; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes da Categoria')
                    ->description('Organize seus ativos por segmentos (ex: Geradores, Munks, etc.)')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome da Categoria')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                                    $operation === 'create' ? $set('slug', Str::slug($state)) : null
                                ),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug (Identificador)')
                                ->disabled()
                                ->dehydrated()
                                ->required(),
                        ]),
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativa para Uso')
                            ->default(true),
                        
                        // Garante que a categoria pertença à empresa logada
                        Forms\Components\Hidden::make('tenant_id')
                            ->default(fn () => Auth::user()->tenant_id),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),
                
                // Exibe quantos ativos estão vinculados a esta categoria
                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Total de Ativos')
                    ->counts('assets')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Apenas Ativas'),
            ])
            /**
             * ATIVA OS BOTÕES DE AÇÃO NO GRID
             */
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetCategories::route('/'),
            'create' => Pages\CreateAssetCategory::route('/create'),
            'edit' => Pages\EditAssetCategory::route('/{record}/edit'),
        ];
    }
}