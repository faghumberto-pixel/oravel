<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartCategoryResource\Pages;
use App\Models\PartCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class PartCategoryResource extends BaseResource
{
    protected static ?string $model = PartCategory::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationParentItem = 'Gestão Almoxarifado';

    protected static ?int $navigationSort = 4;

    protected static ?string $label = 'Categoria de Peça';

    protected static ?string $pluralLabel = 'Categorias de Peças';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados da Categoria')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Categoria')
                        ->placeholder('Filtros e Óleos')
                        ->required()
                        ->maxLength(255)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if ($state) {
                                $set('slug', \Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug (URL)')
                        ->readOnly()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrição')
                        ->placeholder('Descreva o tipo de peça desta categoria...')
                        ->rows(4),
                ]),
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

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parts_count')
                    ->label('Peças')
                    ->counts('parts')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartCategories::route('/'),
            'create' => Pages\CreatePartCategory::route('/create'),
            'view' => Pages\ViewPartCategory::route('/{record}'),
            'edit' => Pages\EditPartCategory::route('/{record}/edit'),
        ];
    }
}
