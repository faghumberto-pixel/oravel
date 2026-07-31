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

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Usuários do Sistema';

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'Gestão SaaS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Usuário')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),

                        Forms\Components\Select::make('tenant_id')
                            ->label('Empresa (Tenant)')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $operation) => $operation === 'create')
                            ->helperText(fn(string $operation) => $operation === 'edit' ? 'Deixe em branco para manter a senha atual' : ''),

                        Forms\Components\Select::make('role')
                            ->label('Função')
                            ->options([
                                'admin' => 'Administrador',
                                'technician' => 'Técnico',
                                'manager' => 'Gerente',
                            ])
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->url(fn(User $record) => route('filament.central.resources.users.edit', $record)),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Função')
                    ->colors([
                        'danger' => 'admin',
                        'warning' => 'manager',
                        'info' => 'technician',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Função')
                    ->options([
                        'admin' => 'Administrador',
                        'technician' => 'Técnico',
                        'manager' => 'Gerente',
                    ]),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Empresa')
                    ->relationship('tenant', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
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
