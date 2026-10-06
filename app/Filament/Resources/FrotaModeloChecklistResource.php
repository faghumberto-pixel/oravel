<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaModeloChecklistResource\Pages;
use App\Models\FrotaItemModeloChecklist as Item;
use App\Models\FrotaModeloChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class FrotaModeloChecklistResource extends BaseResource
{
    protected static ?string $model = FrotaModeloChecklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Modelos de Checklist';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Modelo de Checklist';

    protected static ?string $pluralModelLabel = 'Modelos de Checklist';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Modelo')->columns(3)->schema([
                Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(191),
                Forms\Components\Select::make('tipo')->label('Tipo')->options(FrotaModeloChecklist::tipoLabels())->default(FrotaModeloChecklist::TIPO_RAPIDO)->required()->native(false),
                Forms\Components\Select::make('tipo_veiculo')->label('Vale para')->options(FrotaModeloChecklist::tipoVeiculoLabels())->placeholder('Todos os veículos')->native(false),
                Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
            ]),
            Forms\Components\Section::make('Itens do checklist')
                ->description('Item CRÍTICO com problema bloqueia a saída do veículo. Arraste para mudar a ordem.')
                ->schema([
                    Forms\Components\Repeater::make('itens')
                        ->relationship('itens')
                        ->orderColumn('ordem')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['descricao'] ?? 'Novo item')
                        ->addActionLabel('+ Adicionar item')
                        ->columns(3)
                        ->schema([
                            Forms\Components\TextInput::make('descricao')->label('O que conferir')->required()->maxLength(191)->columnSpan(2),
                            Forms\Components\Select::make('categoria')->label('Categoria')->options(Item::categoriaLabels())->default('outros')->required()->native(false),
                            Forms\Components\Select::make('gravidade')->label('Gravidade')->options(Item::gravidadeLabels())->default(Item::GRAVIDADE_ATENCAO)->required()->native(false),
                            Forms\Components\Select::make('tipo_resposta')->label('Tipo de resposta')->options(Item::tipoRespostaLabels())->default(Item::RESPOSTA_OK_PROBLEMA)->required()->live()->native(false),
                            Forms\Components\TextInput::make('unidade')->label('Unidade')->placeholder('mm, V, bar...')->maxLength(20)
                                ->visible(fn (Forms\Get $get) => $get('tipo_resposta') === Item::RESPOSTA_NUMERO),
                            Forms\Components\Toggle::make('exige_foto_se_problema')->label('Exigir foto se houver problema')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => FrotaModeloChecklist::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('tipo_veiculo')->label('Vale para')->formatStateUsing(fn (?string $state) => FrotaModeloChecklist::tipoVeiculoLabels()[$state] ?? 'Todos')->toggleable(),
                Tables\Columns\TextColumn::make('itens_count')->label('Itens')->counts('itens'),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (FrotaModeloChecklist $record) => ! $record->checklists()->exists())
                    ->modalDescription('Só é possível excluir um modelo que nunca foi usado. Para os demais, desmarque "Ativo".'),
            ])
            ->emptyStateHeading('Nenhum modelo ainda')
            ->emptyStateDescription('Os modelos padrão (rápido e completo) são criados na primeira vez que um checklist é aberto pelo celular.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrotaModeloChecklists::route('/'),
            'create' => Pages\CreateFrotaModeloChecklist::route('/create'),
            'edit' => Pages\EditFrotaModeloChecklist::route('/{record}/edit'),
        ];
    }
}
