<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Gestão SaaS';
    protected static ?string $navigationLabel = 'Planos de Assinatura';
    protected static ?string $pluralModelLabel = 'Planos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configurações Básicas')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Plano')
                        ->placeholder('Ex: Standard - Sócio Fundador')
                        ->required()
                        ->maxLength(255),

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
                ->description('Insira os valores de tabela e defina descontos especiais para Sócios Fundadores ou Fidelidade.')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('base_price')
                        ->label('Preço Base (Mensal bruto)')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),

                    Forms\Components\Select::make('discount_type')
                        ->label('Tipo de Desconto')
                        ->options([
                            'fixed' => 'Valor Fixo (R$)',
                            'percentage' => 'Porcentagem (%)',
                        ])
                        ->default('fixed')
                        ->required(),

                    Forms\Components\TextInput::make('discount_value')
                        ->label('Desconto a Aplicar')
                        ->numeric()
                        ->default(0.00)
                        ->helperText('Ex: 500.00 para Fixo ou 20 para 20%'),

                    Forms\Components\TextInput::make('campaign_tag')
                        ->label('Tag de Campanha (Opcional)')
                        ->placeholder('Ex: socio_fundador')
                        ->maxLength(255)
                        ->helperText('Identificador para rastrear esta oferta comercial nas métricas.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Plano Disponível para Venda')
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make('Funcionalidades e Barramentos do ERP')
                ->description('Associe as chaves do pacote lógico para limitar ou liberar os recursos do cliente.')
                ->schema([
                    Forms\Components\CheckboxList::make('features')
                        ->label('Módulos Inclusos no Plano')
                        ->options([
                            'plano_basic' => 'Módulo Básico (Materiais, Ativos, Clientes, Usuários e Chat)',
                            'plano_standard' => 'Módulo Standard (Ordens de Serviço, Kanban, Preventivas, Custos e Checklists)',
                            'plano_premium' => 'Módulo Premium (Contratos de Locação, Mobilização, Compras, ROI e Métricas)',
                        ])
                        ->required()
                        ->bulkToggleable() // Facilita marcar/desmarcar todos de uma vez
                        ->columns(1),
                ]),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Plano')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('billing_cycle')
                ->label('Ciclo')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'monthly' => 'gray',
                    'quarterly' => 'info',
                    'semiannual' => 'warning',
                    'annual' => 'success',
                    default => 'primary',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'monthly' => 'Mensal',
                    'quarterly' => 'Trimestral',
                    'semiannual' => 'Semestral',
                    'annual' => 'Anual',
                    default => $state,
                }),

            Tables\Columns\TextColumn::make('base_price')
                ->label('Preço Tabela')
                ->money('BRL')
                ->sortable(),

            Tables\Columns\TextColumn::make('final_price')
                ->label('Preço Cobrado (Líquido)')
                ->money('BRL')
                ->fontFamily('mono')
                ->weight('bold')
                ->color('success')
                ->sortable(),

            Tables\Columns\TextColumn::make('campaign_tag')
                ->label('Campanha')
                ->badge()
                ->placeholder('Nenhuma')
                ->color('primary'),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Ativo')
                ->boolean(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('billing_cycle')
                ->label('Ciclo de Cobrança')
                ->options([
                    'monthly' => 'Mensal',
                    'quarterly' => 'Trimestral',
                    'semiannual' => 'Semestral',
                    'annual' => 'Anual',
                ]),
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Apenas Ativos'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
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