<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobRoleResource\Pages;
use App\Models\JobRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class JobRoleResource extends BaseResource
{
    protected static ?string $model = JobRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Equipe';

    protected static ?string $navigationLabel = 'Funções e Cargos';

    protected static ?string $modelLabel = 'Função';

    protected static ?string $pluralModelLabel = 'Funções';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome da função')
                ->helperText('Ex: Vendedor, Técnico, Analista, Supervisor...')
                ->required()
                ->maxLength(191),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Colaboradores')
                    ->counts('employees'),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageJobRoles::route('/')];
    }
}
