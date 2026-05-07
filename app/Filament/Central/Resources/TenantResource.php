<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Clientes (Tenants)';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    
    // Agrupa este item no menu lateral e o coloca na primeira posição
    protected static ?string $navigationGroup = 'Gestão SaaS';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome da Empresa')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255),
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
                    ->label('MRR (Mensalidade)')
                    ->numeric()
                    ->default(0.00)
                    ->prefix('R$'),
                Forms\Components\Toggle::make('onboarding_completed')
                    ->label('Onboarding Concluído')
                    ->default(false),
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
                    
                TextColumn::make('status')
                    ->label('Status da Conta')
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
                    ->label('Cliente desde')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                // Filtros futuros
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