<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card'; // Ícone mais apropriado
    protected static ?string $navigationLabel = 'Planos de Assinatura';
    protected static ?string $modelLabel = 'Plano';
    protected static ?string $pluralModelLabel = 'Planos';
    
    // Agrupa no mesmo menu da Gestão SaaS para ficar organizado
    protected static ?string $navigationGroup = 'Gestão SaaS';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configurações do Plano')
                    ->description('Defina o nome, valor e ciclo de faturamento.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome do Plano')
                            ->placeholder('Ex: Básico, Pro, Enterprise')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('price')
                            ->label('Preço Mensal (Base)')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),
                            
                        Select::make('billing_cycle')
                            ->label('Ciclo de Cobrança')
                            ->options([
                                'monthly' => 'Mensal',
                                'yearly' => 'Anual',
                            ])
                            ->default('monthly')
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Ativo para Novos Clientes')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),

                Section::make('Funcionalidades e Limites')
                    ->description('Liste o que este plano oferece (ajuda no controle de acesso).')
                    ->schema([
                        // O Repeater permite você adicionar várias linhas de benefícios
                        Repeater::make('features')
                            ->label('Recursos Inclusos')
                            ->schema([
                                TextInput::make('feature')
                                    ->label('Nome do Recurso')
                                    ->placeholder('Ex: Relatórios Avançados, Suporte 24h')
                                    ->required(),
                            ])
                            ->columns(1)
                            ->grid(2)
                            ->createItemButtonLabel('Adicionar Recurso'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Plano')
                    ->sortable()
                    ->searchable(),
                    
                TextColumn::make('price')
                    ->label('Preço Base')
                    ->money('BRL')
                    ->sortable(),
                    
                TextColumn::make('billing_cycle')
                    ->label('Ciclo')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => $state === 'monthly' ? 'Mensal' : 'Anual'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),

                TextColumn::make('tenants_count')
                    ->label('Assinantes')
                    ->counts('tenants'), // Mostra quantos clientes usam esse plano
            ])
            ->filters([
                //
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}