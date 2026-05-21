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
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{ 
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?string $navigationLabel = 'Funcionários';
    protected static ?string $pluralModelLabel = 'Funcionários';
    protected static ?string $modelLabel = 'Funcionário';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Usuário')
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->label('Departamento')
                            ->relationship(
                                'department', 
                                'name', 
                                fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id)
                            )
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('roles', [])),

                        Forms\Components\Select::make('roles')
                            ->label('Função / Perfil')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->disabled(fn (Forms\Get $get) => !$get('department_id')),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Funcionário')
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
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->revealable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Função / Cargo')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('hourly_rate')->label('Vlr. Hora')->money('BRL'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    // Garante o carregamento das Roles ao abrir a edição
    public static function mutateFormDataBeforeFill(array $data): array
    {
        $user = User::find($data['id']);
        if ($user) {
            $data['roles'] = $user->roles->pluck('id')->toArray();
        }
        return $data;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('tenants', fn (Builder $query) => $query->where('tenants.id', Filament::getTenant()?->id));
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