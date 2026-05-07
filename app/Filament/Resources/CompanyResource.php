<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'ADMINISTRACAO';
    protected static ?string $navigationLabel = 'Empresa';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados da Empresa')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Empresa')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('address')
                        ->label('Endereço')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('city')
                        ->label('Cidade')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('state')
                        ->label('Estado')
                        ->maxLength(2),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone')
                        ->tel()
                        ->maxLength(20),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
            Tables\Columns\TextColumn::make('city')->label('Cidade'),
            Tables\Columns\TextColumn::make('phone')->label('Telefone'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}