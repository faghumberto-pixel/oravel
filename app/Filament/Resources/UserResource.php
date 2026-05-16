<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Facades\Filament;

class UserResource extends Resource
{ 
    protected static ?string $model = User::class;

    // CORREÇÃO 1: Ativa o menu na barra lateral para renderizar o submenu de usuários
    protected static bool $shouldRegisterNavigation = true;

    // Vincula o recurso ao escopo do Tenant ativo
    protected static bool $isScopedToTenant = true;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?string $navigationLabel = 'Funcionários';
    protected static ?string $pluralModelLabel = 'Funcionários';
    protected static ?string $modelLabel = 'Funcionário';
    
    // ORDENAÇÃO: 1º Departamentos, 2º Funções, 3º Funcionários
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Usuário')
                    ->schema([
                        // NOVO: Seleção de Departamento (Ponto de partida do cadastro)
                        Forms\Components\Select::make('department_id')
                            ->label('Departamento')
                            ->relationship(
                                'department', 
                                'name', 
                                fn ($query) => $query->where('tenant_id', Filament::getTenant()?->id)
                            )
                            ->placeholder('Selecione o Departamento')
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('roles', null)), // Limpa a função se mudar o setor

                        // AJUSTE: Filtra as Funções de acordo com o Departamento selecionado acima
                        Forms\Components\Select::make('roles')
                            ->label('Função / Perfil')
                            ->relationship(
                                'roles', 
                                'name',
                                fn ($query, Forms\Get $get) => $query
                                    ->where('department_id', $get('department_id'))
                            )
                            ->preload()
                            ->searchable()
                            ->required()
                            ->disabled(fn (Forms\Get $get) => !$get('department_id')), // Travado até escolher o setor

                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Funcionário')
                            ->placeholder('Nome Completo')
                            ->required(),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Valor da Hora (Custo)')
                            ->prefix('R$')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->revealable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // HIERARQUIA NO GRID: 1ª DIRETRIZ - O Departamento vem sempre primeiro
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                // HIERARQUIA NO GRID: 2ª DIRETRIZ - A Função/Cargo vem em segundo lugar
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Função / Cargo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'Gerente de Manutenção' => 'danger',
                        'Supervisor de Manutenção' => 'warning',
                        default => 'info',
                    })
                    ->sortable()
                    ->searchable(),

                // HIERARQUIA NO GRID: 3ª DIRETRIZ - Nome do Funcionário
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Funcionário')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('Vlr. Hora')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}