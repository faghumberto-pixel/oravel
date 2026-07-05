<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

#[BelongsToFeature('users')]
class UserResource extends Resource
{
    protected static ?string $tenantRelationshipName = 'tenants';

    // Isolamento manual via Auth::user()->tenant_id (NÃO usamos tenancy nativa do Filament).
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Equipe';

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
                                name: 'department',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    $tenant = Tenancy::current();

                                    return $query
                                        ->when($tenant, fn (Builder $q) => $q->where('tenant_id', $tenant->id))
                                        ->orderBy('name');
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('roles', [])),

                        Forms\Components\Select::make('roles')
                            ->label('Função / Perfil')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    $user = Auth::user();

                                    if ($user && ! $user->isSuperAdmin() && $user->tenant_id) {
                                        $query->where('roles.tenant_id', $user->tenant_id);
                                    }

                                    return $query;
                                },
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->disabled(fn (Forms\Get $get) => ! $get('department_id')),

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
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->label('Departamento'),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Função')
                    ->options([
                        'admin' => 'Administrador',
                        'tecnico' => 'Técnico',
                        'colaborador' => 'Colaborador',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        // Console/seeder (sem usuário) ou super admin → sem filtro
        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        // Usuário sem tenant_id → sem filtro (mesma política da trait BelongsToTenant)
        if (! $user->tenant_id) {
            return $query;
        }

        // Admin/funcionário → só usuários do próprio tenant
        return $query->where('users.tenant_id', $user->tenant_id);
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
