<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages\CreateDepartment;
use App\Filament\Resources\DepartmentResource\Pages\EditDepartment;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Models\Department;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    // FIX 1: Desativa o escopo automático do tenant para evitar a busca por relações
    protected static bool $isScopedToTenant = false;

    // FIX 2: Garante que não busque relação, mesmo que o escopo fosse ativado
    protected static ?string $tenantRelationshipName = null;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Equipe';

    protected static ?string $navigationLabel = 'Departamentos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // FIX: Injeção manual do tenant para garantir integridade, se necessário
            Forms\Components\Hidden::make('tenant_id')
                ->default(fn () => Tenancy::current()?->id),

            Forms\Components\Section::make('Informações do Departamento')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Departamento')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('sector_key')
                        ->label('Setor padrão')
                        ->helperText('Opcional. Identifica este departamento como um dos setores padrão do sistema (ex: Logística) -- necessário pra telas que exigem um nível hierárquico mínimo naquele setor específico. Deixe em branco pra um departamento customizado, sem esse vínculo.')
                        ->options(Department::sectorLabels())
                        ->native(false),
                ]),
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
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
