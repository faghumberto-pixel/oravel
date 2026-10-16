<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmProdutoResource\Pages;
use App\Models\CrmLeadItem;
use App\Models\CrmProduto;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

/** Catálogo simples de produtos e serviços do CRM (não depende de Materiais nem de Ativos do ERP). */
class CrmProdutoResource extends BaseResource
{
    protected static ?string $model = CrmProduto::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Produtos e Serviços';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Produto/Serviço';

    protected static ?string $pluralModelLabel = 'Produtos e Serviços';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(255)->columnSpan(2),
            Forms\Components\TextInput::make('codigo')->label('Código (opcional)')->maxLength(40)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', Tenancy::current()?->id)),
            Forms\Components\Select::make('tipo')->label('Tipo')->options(CrmProduto::tipoLabels())->default(CrmProduto::TIPO_SERVICO)->required(),
            Forms\Components\Select::make('unidade')->label('Unidade de venda')->options(CrmProduto::unidadeLabels())->default('un')->required(),
            Forms\Components\TextInput::make('preco')->label('Preço (R$)')->numeric()->prefix('R$')->minValue(0)->default(0)->required(),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')->label('Código')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn ($state) => CrmProduto::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('unidade')->label('Unidade')->formatStateUsing(fn ($state) => CrmProduto::unidadeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('preco')->label('Preço')->money('BRL')->sortable(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(CrmProduto::tipoLabels()),
                Tables\Filters\TernaryFilter::make('ativo')->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->before(function (CrmProduto $record, Tables\Actions\DeleteAction $action) {
                    if (CrmLeadItem::withoutGlobalScopes()->where('crm_produto_id', $record->id)->exists()) {
                        Notification::make()->danger()->title('Item em uso')->body('Há leads com este item de interesse. Desative-o em vez de excluir.')->send();
                        $action->cancel();
                    }
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCrmProdutos::route('/')];
    }
}
