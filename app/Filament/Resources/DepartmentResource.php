<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $navigationGroup = 'ADMINISTRACAO';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração de Departamento')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Departamento')
                            ->required()
                            ->maxLength(255)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $prefix = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $state)), 0, 5);
                                // Conta os registros existentes do tenant para o sequencial
                                $count = Department::where('tenant_id', Auth::user()->tenant_id)->count() + 1;
                                $code = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
                                $set('code', $code);
                            }),
                        
                        Forms\Components\TextInput::make('code')
                            ->label('Código Sequencial')
                            ->required()
                            ->disabled()
                            ->dehydrated() 
                            ->maxLength(8), // Aumentado para comportar prefixo(5) + numero(3)
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Departamento')->searchable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    // Usamos o método handleRecordCreation para garantir que o tenant seja inserido 
    // antes da validação do banco de dados (evita o erro NOT NULL)
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        
        // Se o código não estiver no $data (por estar disabled), calculamos aqui novamente por segurança
        if (empty($data['code'])) {
            $prefix = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $data['name'])), 0, 5);
            $count = Department::where('tenant_id', $data['tenant_id'])->count() + 1;
            $data['code'] = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
        }
        
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}