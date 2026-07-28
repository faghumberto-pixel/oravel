<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Planos de Assinatura';

    protected static ?string $pluralModelLabel = 'Planos';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configurações Básicas')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Plano')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('level')
                        ->label('Nível de Hierarquia')
                        ->options([
                            1 => '1 - Track (Básico)',
                            2 => '2 - Flow (Standard)',
                            3 => '3 - Alpha (Premium)',
                        ])
                        ->required(),

                    Forms\Components\Select::make('billing_cycle')
                        ->label('Ciclo de Cobrança')
                        ->options([
                            'monthly' => 'Mensal',
                            'quarterly' => 'Trimestral',
                            'semiannual' => 'Semestral',
                            'annual' => 'Anual',
                        ])
                        ->default('monthly')
                        ->required(),
                ]),

            Forms\Components\Section::make('Estratégia de Precificação e Campanhas')
                ->schema([
                    Forms\Components\TextInput::make('base_price')
                        ->label('Preço Base')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),

                    Forms\Components\Select::make('discount_type')
                        ->label('Tipo de Desconto')
                        ->options([
                            'fixed' => 'Valor Fixo (R$)',
                            'percentage' => 'Porcentagem (%)',
                        ]),

                    Forms\Components\TextInput::make('discount_value')
                        ->label('Desconto')
                        ->numeric()
                        ->default(0.00),

                    Forms\Components\TextInput::make('campaign_tag')
                        ->maxLength(255),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo para Venda')
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make('Tabelas e Recursos Disponíveis no Plano')
                ->description('Selecione detalhadamente quais recursos e tabelas estarão visíveis e operáveis para este plano.')
                ->schema([
                    Forms\Components\CheckboxList::make('features')
                        ->label('Módulos Liberados')
                        // Executa o método estático que adicionamos ao modelo Plan para renderizar as opções
                        ->options(Plan::getAvailableFeaturesOptions())
                        ->bulkToggleable()
                        ->required(),
                    // REMOVIDOS os ganchos manuais de hidratação e desidratação.
                    // O Filament gerencia nativamente o array linear com o cast do Laravel 12.
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Plano')->weight('bold')->searchable(),
            // Cor por nivel (1 Track / 2 Flow / 3 Alpha), nao mais texto
            // pelado -- pedido do usuario 2026-07-28 ("mesmo padrao, mais
            // colorido e vivo").
            Tables\Columns\TextColumn::make('level')
                ->label('Nível')
                ->badge()
                ->formatStateUsing(fn (int $state) => match ($state) {
                    1 => 'Track',
                    2 => 'Flow',
                    3 => 'Alpha',
                    default => (string) $state,
                })
                ->color(fn (int $state) => match ($state) {
                    1 => 'gray',
                    2 => 'crmBlue',
                    3 => 'crmPurple',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('base_price')->label('Preço Tabela')->money('BRL')->weight('bold'),
            Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
        ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
