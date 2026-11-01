<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\AcompanhamentoResource\Pages;
use App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers;
use App\Models\Acompanhamento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Acompanhamentos do painel Central (so' super admin, menu Comercial): cada um guarda uma previa opcional,
 * uma lista de itens (paginas, fontes, concorrentes...), notas por data e pendencias/decisoes. Para uma nova
 * pesquisa, crie um novo acompanhamento e cole a lista.
 */
class AcompanhamentoResource extends Resource
{
    protected static ?string $model = Acompanhamento::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'acompanhamento';

    protected static ?string $pluralModelLabel = 'Acompanhamentos';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(160)->columnSpanFull(),
            Forms\Components\Select::make('tipo')->label('Tipo')->options(Acompanhamento::TIPOS)->default('projeto')->required()->native(false),
            Forms\Components\Select::make('status')->label('Situação')->options(Acompanhamento::STATUS)->default('ativo')->required()->native(false),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('base_url')->label('Endereço base dos itens (opcional)')->url()->placeholder('https://oravel.com.br')
                ->helperText('Itens cuja referência começa com "/" viram links com este prefixo.')->columnSpanFull(),
            Forms\Components\FileUpload::make('previa_arquivo')->label('Prévia (arquivo HTML, opcional)')->disk('local')->directory('acompanhamentos')
                ->acceptedFileTypes(['text/html'])->maxSize(10240)->visibility('private')->columnSpanFull(),
            Forms\Components\TextInput::make('previa_url')->label('Link externo da prévia (opcional)')->url()->maxLength(300)->columnSpanFull(),
        ])->columns(2);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('descricao')->hiddenLabel()->placeholder('Sem descrição.')->columnSpanFull(),
            Infolists\Components\TextEntry::make('previa_url')->label('Prévia externa')->url(fn (Acompanhamento $r): ?string => $r->previa_url, true)
                ->visible(fn (Acompanhamento $r): bool => filled($r->previa_url))->columnSpanFull(),
            Infolists\Components\ViewEntry::make('previa')->hiddenLabel()->view('filament.central.acompanhamento-previa')
                ->visible(fn (Acompanhamento $r): bool => filled($r->previa_arquivo))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Acompanhamento')->weight('bold')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->color('gray')->formatStateUsing(fn (string $state): string => Acompanhamento::TIPOS[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state): string => Acompanhamento::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'ativo' => 'success', 'concluido' => 'info', default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('itens_count')->label('Itens')->counts('itens'),
                Tables\Columns\TextColumn::make('pendencias_abertas_count')->label('Pendências em aberto')
                    ->counts(['pendencias as pendencias_abertas_count' => fn ($q) => $q->where('status', '!=', 'concluida')]),
                Tables\Columns\TextColumn::make('updated_at')->label('Atualizado')->since()->sortable(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(Acompanhamento::STATUS)->label('Situação')])
            ->actions([Tables\Actions\ViewAction::make()->label('Abrir'), Tables\Actions\EditAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PendenciasRelationManager::class,
            RelationManagers\ItensRelationManager::class,
            RelationManagers\NotasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcompanhamentos::route('/'),
            'create' => Pages\CreateAcompanhamento::route('/create'),
            'view' => Pages\ViewAcompanhamento::route('/{record}'),
            'edit' => Pages\EditAcompanhamento::route('/{record}/edit'),
        ];
    }
}
