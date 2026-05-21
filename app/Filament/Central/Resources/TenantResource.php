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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Clientes (Tenants)';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    
    protected static ?string $navigationGroup = 'Gestão SaaS';
    protected static ?int $navigationSort = 1;

    /**
     * Garante que o Filament use o UUID para encontrar o registro na URL
     * Isso previne o erro 404 ao tentar editar um Tenant
     */
    public static function getRecordRouteKeyName(): ?string
    {
        return 'id';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identificação')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Empresa')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ]),

                // --- NOVA SEÇÃO: CRIAÇÃO AUTOMÁTICA DO DONO ---
                Section::make('Usuário Administrador (Dono)')
                    ->description('Este usuário será criado automaticamente e vinculado como administrador desta empresa.')
                    ->visibleOn('create') // Só aparece na tela de "Novo Cliente"
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('admin_name')
                            ->label('Nome Completo')
                            ->required()
                            ->dehydrated(false), // Impede que o Filament tente salvar isso na tabela 'tenants'

                        Forms\Components\TextInput::make('admin_email')
                            ->label('E-mail de Acesso')
                            ->email()
                            ->required()
                            ->unique('users', 'email') // Verifica se o e-mail já existe no banco inteiro
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('admin_password')
                            ->label('Senha Provisória')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->dehydrated(false),
                    ]),

                Section::make('Assinatura e Plano')
                    ->columns(2)
                    ->schema([
                        Select::make('plan_id')
                            ->label('Plano de Assinatura')
                            ->relationship('plan', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $plan = Plan::find($state);
                                    $set('mrr_value', $plan?->price ?? 0);
                                }
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'trial' => 'Trial (Teste)',
                                'active' => 'Ativo',
                                'past_due' => 'Inadimplente',
                                'canceled' => 'Cancelado',
                            ])
                            ->default('trial')
                            ->required(),

                        Forms\Components\TextInput::make('mrr_value')
                            ->label('Valor da Mensalidade (MRR)')
                            ->numeric()
                            ->default(0.00)
                            ->prefix('R$')
                            ->helperText('Valor real cobrado mensalmente deste cliente'),
                    ]),

                Section::make('Configurações')
                    ->schema([
                        Forms\Components\Toggle::make('onboarding_completed')
                            ->label('Onboarding Concluído')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa Cliente')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('plan.name')
                    ->label('Plano')
                    ->sortable(),
                    
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trial' => 'warning',
                        'active' => 'success',
                        'past_due', 'canceled' => 'danger',
                        default => 'primary',
                    }),

                TextColumn::make('mrr_value')
                    ->label('MRR')
                    ->money('BRL')
                    ->sortable(),

                IconColumn::make('onboarding_completed')
                    ->label('Onboarding')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('created_at')
                    ->label('Desde')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label('Filtrar por Plano')
                    ->relationship('plan', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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