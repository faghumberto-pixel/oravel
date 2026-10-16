<?php

namespace App\Filament\Resources\CrmLeadResource\RelationManagers;

use App\Models\CrmLeadItem;
use App\Models\CrmProduto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Itens de interesse do lead (catálogo de produtos/serviços), com a soma que pode virar o valor estimado. */
class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Itens de interesse';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('crm_produto_id')
                ->label('Produto/Serviço do catálogo')
                ->options(fn () => CrmProduto::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    if ($state && ($produto = CrmProduto::find($state))) {
                        $set('preco_unitario', (float) $produto->preco);
                        $set('descricao', $produto->nome);
                    }
                })
                ->helperText('Ou deixe vazio e descreva um item livre.'),
            Forms\Components\TextInput::make('descricao')->label('Descrição')->maxLength(255)
                ->required(fn (Forms\Get $get) => blank($get('crm_produto_id'))),
            Forms\Components\TextInput::make('quantidade')->label('Quantidade')->numeric()->minValue(0.01)->default(1)->required(),
            Forms\Components\TextInput::make('preco_unitario')->label('Preço unitário')->numeric()->prefix('R$')->minValue(0)->default(0)->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descricao')
            ->columns([
                Tables\Columns\TextColumn::make('descricao')->label('Item'),
                Tables\Columns\TextColumn::make('quantidade')->label('Qtd'),
                Tables\Columns\TextColumn::make('preco_unitario')->label('Preço')->money('BRL'),
                Tables\Columns\TextColumn::make('subtotal')->label('Subtotal')->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adicionar item'),
                Tables\Actions\Action::make('usarComoValor')
                    ->label('Usar o total como valor estimado')
                    ->icon('heroicon-o-calculator')
                    ->color('gray')
                    ->action(function () {
                        $lead = $this->getOwnerRecord();
                        $total = (float) CrmLeadItem::where('crm_lead_id', $lead->id)->sum('subtotal');
                        $lead->update(['estimated_value' => $total]);

                        Notification::make()->title('Valor estimado atualizado para R$ '.number_format($total, 2, ',', '.'))->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
