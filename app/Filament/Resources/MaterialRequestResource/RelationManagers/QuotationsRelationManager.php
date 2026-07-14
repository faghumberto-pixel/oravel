<?php

namespace App\Filament\Resources\MaterialRequestResource\RelationManagers;

use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QuotationsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotations';

    protected static ?string $title = 'Cotações';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('supplier_id')
                ->label('Fornecedor')
                ->relationship('supplier', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('total_value')
                ->label('Valor Total Cotado')
                ->numeric()
                ->prefix('R$')
                ->required(),
            Forms\Components\TextInput::make('delivery_days')
                ->label('Prazo de Entrega (dias)')
                ->numeric(),
            Forms\Components\TextInput::make('payment_terms')
                ->label('Condição de Pagamento')
                ->maxLength(255),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('supplier.name')
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')->label('Fornecedor'),
                Tables\Columns\TextColumn::make('total_value')->label('Valor Total')->money('BRL'),
                Tables\Columns\TextColumn::make('delivery_days')->label('Prazo')->suffix(' dias')->placeholder('—'),
                Tables\Columns\TextColumn::make('payment_terms')->label('Pagamento')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_selected')->label('Selecionada')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('selecionar')
                    ->label('Selecionar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Model $record) => ! $record->is_selected)
                    ->action(function (Model $record) {
                        // So' uma cotacao selecionada por requisicao -- desmarca
                        // as outras antes de marcar esta.
                        $record->materialRequest->quotations()->update(['is_selected' => false]);
                        $record->update(['is_selected' => true]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
