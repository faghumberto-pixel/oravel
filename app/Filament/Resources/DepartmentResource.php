<?php

namespace App\Filament\Resources;

use App\Models\Department;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Facades\Filament;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    // FIX 1: Desativa o escopo automático do tenant para evitar a busca por relações
    protected static bool $isScopedToTenant = false;

    // FIX 2: Garante que não busque relação, mesmo que o escopo fosse ativado
    protected static ?string $tenantRelationshipName = null;


    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?string $navigationLabel = 'Departamentos';


    public static function form(Form $form): Form
    {
        return $form->schema([
            // FIX: Injeção manual do tenant para garantir integridade, se necessário
            Forms\Components\Hidden::make('tenant_id')
                ->default(fn () => \App\Support\Tenancy::current()?->id),

            Forms\Components\Section::make('Informações do Departamento')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Departamento')
                        ->required()
                        ->maxLength(255),
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i'),
        ])
        ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
        ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\DepartmentResource\Pages\ListDepartments::route('/'),
            'create' => \App\Filament\Resources\DepartmentResource\Pages\CreateDepartment::route('/create'),
            'edit' => \App\Filament\Resources\DepartmentResource\Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}