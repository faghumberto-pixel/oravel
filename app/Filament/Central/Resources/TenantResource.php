<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Plan;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Empresas (Tenants)';
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Gestão SaaS';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informações da Empresa')->schema([
                Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
                Forms\Components\TextInput::make('slug')->label('Slug')->unique(Tenant::class, 'slug', ignoreRecord: true)->required()->maxLength(255),
                Forms\Components\Select::make('plan_id')->label('Plano')->relationship('plan', 'name')->searchable()->preload(),
                Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Ativo', 'trial' => 'Teste', 'suspended' => 'Suspenso', 'canceled' => 'Cancelado'])->default('trial')->required(),
                Forms\Components\TextInput::make('mrr_value')->label('MRR (R$)')->numeric()->step(0.01)->default(0),
                Forms\Components\Toggle::make('onboarding_completed')->label('Onboarding Completo')->default(false),
            ])->columns(2),

            Forms\Components\Section::make('Administrador do Tenant')
                ->description('Este usuário nasce com o papel "admin": acesso total a tudo que o plano contratado libera, e pode criar outros usuários e perfis de acesso personalizados dentro da própria empresa.')
                ->schema([
                    Forms\Components\TextInput::make('admin_name')
                        ->label('Nome do Administrador')
                        ->required()
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('admin_email')
                        ->label('E-mail do Administrador')
                        ->email()
                        ->required()
                        ->unique(\App\Models\User::class, 'email')
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('admin_password')
                        ->label('Senha')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->visibleOn('create'),
                ])
                ->visibleOn('create')
                ->columns(3),

            Forms\Components\Section::make('🔐 Recursos Adicionais')
                ->description('Libere aqui módulos além do que o plano contratado já concede. Deixar desmarcado não bloqueia nada do plano — só o plano define o que é negado.')
                ->schema([
                    Forms\Components\CheckboxList::make('features')
                        ->label('Módulos extras liberados para este tenant')
                        ->options(Plan::getAvailableFeaturesOptions())
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Empresa')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->label('Slug')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('plan.name')->label('Plano')->sortable(),
            Tables\Columns\BadgeColumn::make('status')->label('Status')->colors(['success' => 'active', 'warning' => 'trial', 'danger' => 'suspended', 'gray' => 'canceled']),
            Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('Status')->options(['active' => 'Ativo', 'trial' => 'Teste']),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ])->defaultSort('created_at', 'desc');
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
