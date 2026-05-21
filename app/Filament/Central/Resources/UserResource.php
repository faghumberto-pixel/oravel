<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Usuários do Sistema';
    protected static ?string $modelLabel = 'Usuário';
    protected static ?string $pluralModelLabel = 'Usuários';
    
    protected static ?string $navigationGroup = 'Gestão SaaS';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Usuário')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->label('Empresa (Cliente)')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan('full'),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                            
                        // FILTRO APLICADO: Central só vê perfis administrativos
                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name', fn ($query) => $query->whereIn('name', ['admin', 'gestor', 'colaborador']))
                            ->label('Nível de Acesso (Central)')
                            ->helperText('Selecione o nível administrativo para este usuário.')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->native(false), 
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
                Tables\Columns\TextColumn::make('tenant.name')->label('Empresa')->badge()->color('info'),
                Tables\Columns\TextColumn::make('roles.name')->label('Funções')->badge()->color('primary')->separator(', '),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        $user = User::find($data['id']);
        if ($user) {
            $data['roles'] = $user->roles->pluck('id')->toArray();
        }
        return $data;
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