<?php

namespace App\Filament\Resources\CrmPropostaResource\RelationManagers;

use App\Models\CrmProduto;
use App\Models\CrmProposta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Itens da proposta (catálogo ou livres). Só o rascunho aceita mudanças; o total da proposta acompanha. */
class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Itens da proposta';

    private function editavel(): bool
    {
        /** @var CrmProposta $p */
        $p = $this->getOwnerRecord();

        return $p->editavel();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('crm_produto_id')->label('Produto/Serviço do catálogo')->searchable()->live()
                ->options(fn () => CrmProduto::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    if ($state && ($p = CrmProduto::find($state))) {
                        $set('preco_unitario', (float) $p->preco);
                        $set('descricao', $p->nome);
                    }
                })->helperText('Ou deixe vazio e descreva um item livre.'),
            Forms\Components\TextInput::make('descricao')->label('Descrição')->maxLength(255)->required(),
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
                Tables\Columns\TextColumn::make('subtotal')->label('Subtotal')->money('BRL')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total dos itens')),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Adicionar item')->visible(fn () => $this->editavel())])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn () => $this->editavel()),
                Tables\Actions\DeleteAction::make()->visible(fn () => $this->editavel()),
            ]);
    }
}
