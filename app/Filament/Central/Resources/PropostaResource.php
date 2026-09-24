<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\PropostaResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Caminho dedicado pra gerar o link público de assinatura (2026-09-23,
 * pedido do usuário: "quero uma tela nova, só pra gerar o link" --
 * ele não queria que esse fluxo continuasse vivendo dentro de "Planos",
 * já que não existe mais plano padrão de prateleira, cada cliente
 * negocia o próprio conjunto de módulos/valor).
 *
 * Por baixo continua sendo o MESMO registro de Plan que PlanResource usa
 * (nenhuma tabela nova, nenhum dado duplicado) -- é só uma segunda porta
 * de entrada mais enxuta, focada só no essencial (nome da proposta, valor,
 * ciclo, módulos) e que já mostra o link assim que a proposta é criada. A
 * tela de "Planos" continua existindo do jeito que estava (o usuário
 * pediu explicitamente pra manter as duas por enquanto).
 */
class PropostaResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Gerar Link de Assinatura';

    protected static ?string $modelLabel = 'Proposta';

    protected static ?string $pluralModelLabel = 'Propostas';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Proposta')
                ->description('Cada cliente tem o próprio conjunto de módulos e valor -- não existe mais plano padrão fechado.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Identificação da proposta')
                        ->placeholder('Ex: Nome do cliente ou da negociação')
                        ->helperText('Uso interno -- não aparece pro cliente, só ajuda você a reconhecer essa proposta depois.')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('base_price')
                        ->label('Valor Mensal')
                        ->numeric()
                        ->prefix('R$')
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

            Forms\Components\Section::make('Módulos incluídos')
                ->description('Selecione o que essa proposta específica libera pro cliente.')
                ->schema([
                    Forms\Components\CheckboxList::make('features')
                        ->label('Módulos')
                        ->options(Plan::getAvailableFeaturesOptions())
                        ->bulkToggleable()
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Proposta')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('base_price')->label('Valor')->money('BRL')->weight('bold'),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Ciclo')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'quarterly' => 'Trimestral',
                        'semiannual' => 'Semestral',
                        'annual' => 'Anual',
                        default => 'Mensal',
                    }),
                Tables\Columns\TextColumn::make('features')
                    ->label('Módulos')
                    ->formatStateUsing(fn (?array $state) => count($state ?? []).' selecionado(s)'),
                Tables\Columns\TextColumn::make('created_at')->label('Criada em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('copy_signup_link')
                    ->label('Copiar Link')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->action(function (Plan $record) {
                        $link = route('checkout.create', ['plano' => $record->id]);

                        Notification::make()
                            ->title('Link de assinatura')
                            ->body($link)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropostas::route('/'),
            'create' => Pages\CreateProposta::route('/create'),
            'edit' => Pages\EditProposta::route('/{record}/edit'),
        ];
    }
}
