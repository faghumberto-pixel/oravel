<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Gestão SaaS';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Empresa')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
                ]),

            Forms\Components\Section::make('Administrador da Empresa')
                ->description('Credenciais do primeiro administrador desta empresa.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('admin_name')
                        ->label('Nome do Administrador')
                        ->required()
                        ->maxLength(255)
                        ->default(fn ($record) => $record?->adminUser?->name)
                        ->disabled(fn (string $operation) => $operation === 'edit'),

                    Forms\Components\TextInput::make('admin_email')
                        ->label('E-mail de Acesso')
                        ->email()
                        ->required()
                        ->default(fn ($record) => $record?->adminUser?->email)
                        ->disabled(fn (string $operation) => $operation === 'edit'),

                    Forms\Components\TextInput::make('admin_password')
                        ->label('Senha Inicial')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->visibleOn('create'),
                ]),

            Forms\Components\Section::make('Assinatura e Plano')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('plan_id')
                        ->label('Plano de Assinatura')
                        ->relationship('plan', 'name')
                        ->searchable()->preload()->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('mrr_value', Plan::find($state)?->final_price ?? 0)),
                    Forms\Components\Select::make('status')
                        ->options(['trial' => 'Trial', 'active' => 'Ativo', 'past_due' => 'Inadimplente', 'canceled' => 'Cancelado']),
                    Forms\Components\TextInput::make('mrr_value')->numeric()->prefix('R$'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Empresa')->searchable(),
            Tables\Columns\TextColumn::make('adminUser.email')->label('Admin'),
            Tables\Columns\TextColumn::make('plan.name')->label('Plano'),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
