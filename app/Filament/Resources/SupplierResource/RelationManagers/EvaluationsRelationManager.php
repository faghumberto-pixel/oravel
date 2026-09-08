<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Historico de scoring do fornecedor -- linhas normalmente criadas pela
 * acao "Avaliar Fornecedor" em GoodsReceiptResource (ver
 * EditGoodsReceipt), mas tambem editaveis/criaveis direto aqui pra
 * avaliacao avulsa.
 */
class EvaluationsRelationManager extends RelationManager
{
    protected static string $relationship = 'evaluations';

    protected static ?string $title = 'Avaliações';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('purchase_order_id')
                ->label('Ordem de Compra (opcional)')
                ->relationship('purchaseOrder', 'id', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->getOptionLabelFromRecordUsing(fn ($record) => '#'.$record->id)
                ->searchable(),
            Forms\Components\Select::make('score_prazo_entrega')
                ->label('Prazo de Entrega')
                ->options(self::scoreOptions())
                ->required()
                ->native(false),
            Forms\Components\Select::make('score_qualidade')
                ->label('Qualidade')
                ->options(self::scoreOptions())
                ->required()
                ->native(false),
            Forms\Components\Select::make('score_preco')
                ->label('Preço')
                ->options(self::scoreOptions())
                ->required()
                ->native(false),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('evaluated_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseOrder.id')
                    ->label('Ordem de Compra')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('score_prazo_entrega')->label('Prazo'),
                Tables\Columns\TextColumn::make('score_qualidade')->label('Qualidade'),
                Tables\Columns\TextColumn::make('score_preco')->label('Preço'),
                Tables\Columns\TextColumn::make('score_medio')
                    ->label('Média')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('evaluatedBy.name')->label('Avaliado por')->placeholder('—'),
            ])
            ->defaultSort('evaluated_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['evaluated_by_user_id'] = auth()->id();
                        $data['evaluated_at'] = now();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    private static function scoreOptions(): array
    {
        return [1 => '1 - Ruim', 2 => '2 - Regular', 3 => '3 - Bom', 4 => '4 - Ótimo', 5 => '5 - Excelente'];
    }
}
