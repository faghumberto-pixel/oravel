<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class RoleResource extends Resource
{ 
    protected static ?string $model = Role::class;
    
    // Impede que o Filament procure por tenant_id nesta tabela global do Spatie
    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?string $navigationLabel = 'Funções';
    protected static ?string $pluralModelLabel = 'Funções';
    
    // Alinha a ordenação para ficar logo após os Departamentos
    protected static ?int $navigationSort = 2;

    /**
     * Força o registro do menu na barra lateral para o Admin Oravel e para o Gestor do Tenant
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('gestor');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Dados da Função')
                ->schema([
                    // HIERARQUIA NO FORMULÁRIO: O departamento vem primeiro para classificar o cargo
                    Forms\Components\Select::make('department_id')
                        ->label('Departamento')
                        ->relationship(
                            'department', 
                            'name', 
                            fn ($query) => $query->where('tenant_id', Filament::getTenant()?->id)
                        )
                        ->placeholder('Selecione o Departamento')
                        ->preload()
                        ->required(),

                    TextInput::make('name')
                        ->label('Nome da Função')
                        ->placeholder('Ex: Técnico Nível 1, Supervisor')
                        ->required()
                        ->unique(ignoreRecord: true),
                ])->columns(2),

            Section::make('Permissões')
                ->description('Selecione as ações permitidas para esta função.')
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name') // Lista TUDO do banco sem travas de options
                        ->label('Lista de Permissões')
                        ->columns(3)
                        ->bulkToggleable()
                        ->searchable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Segurança: Se não for o Admin mestre da Oravel, esconde as funções mestre do sistema
                if (!auth()->user()?->hasRole('admin')) {
                    return $query->whereNotIn('name', ['admin', 'gestor']);
                }
                return $query;
            })
            ->columns([
                // HIERARQUIA NO GRID: 1º DIRETRIZ - O Departamento vem sempre primeiro
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                // HIERARQUIA NO GRID: 2º DIRETRIZ - O Nome da Função/Cargo
                Tables\Columns\TextColumn::make('name')
                    ->label('Função / Cargo')
                    ->searchable()
                    ->sortable(),

                // HIERARQUIA NO GRID: 3º DIRETRIZ - Quantos funcionários reais usam essa função ativa
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Funcionários Ativos')
                    ->counts('users')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissões Ativas')
                    ->counts('permissions')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}