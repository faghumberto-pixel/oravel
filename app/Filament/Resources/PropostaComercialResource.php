<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropostaComercialResource\Pages;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tela do time Comercial pra revisar propostas enviadas pelo vendedor de
 * campo. Não tem Create/Edit -- a proposta só é editada pelo próprio
 * vendedor (no wizard mobile, enquanto rascunho); o Comercial só visualiza
 * e aciona as Actions de aprovar/rejeitar (ver ViewPropostaComercial).
 */
class PropostaComercialResource extends BaseResource
{
    protected static ?string $model = PropostaComercial::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Proposta Comercial';

    protected static ?string $pluralModelLabel = 'Propostas Comerciais';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Identificação')
                ->columns(3)
                ->schema([
                    TextEntry::make('client.name')->label('Cliente'),
                    TextEntry::make('sellerUser.name')->label('Vendedor'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => PropostaComercial::statusLabels()[$state] ?? $state),
                    TextEntry::make('valid_until')->label('Válida até')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('total_value')->label('Valor Total')->money('BRL'),
                    TextEntry::make('sent_at')->label('Enviada em')->dateTime('d/m/Y H:i')->placeholder('—'),
                ]),

            Section::make('Itens')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            TextEntry::make('type')
                                ->label('Tipo')
                                ->formatStateUsing(fn (string $state) => PropostaComercialItem::typeLabels()[$state] ?? $state),
                            TextEntry::make('description')->label('Descrição'),
                            TextEntry::make('quantity')->label('Qtd.'),
                            TextEntry::make('unit_price')->label('Valor Unit.')->money('BRL'),
                            TextEntry::make('subtotal')->label('Subtotal')->money('BRL'),
                            TextEntry::make('start_date')->label('Início')->date('d/m/Y')->placeholder('—'),
                        ])
                        ->columns(6),
                ]),

            Section::make('Termos')
                ->schema([
                    TextEntry::make('terms')->label('')->placeholder('Sem termos definidos.')->columnSpanFull(),
                ]),

            Section::make('Revisão')
                ->visible(fn (PropostaComercial $record) => $record->status !== PropostaComercial::STATUS_RASCUNHO)
                ->columns(2)
                ->schema([
                    TextEntry::make('reviewedByUser.name')->label('Revisado por')->placeholder('—'),
                    TextEntry::make('reviewed_at')->label('Em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('rejection_reason')->label('Motivo da Rejeição')->placeholder('—')->columnSpanFull()
                        ->visible(fn (PropostaComercial $record) => $record->status === PropostaComercial::STATUS_REJEITADA),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sellerUser.name')
                    ->label('Vendedor'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PropostaComercial::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL => 'info',
                        PropostaComercial::STATUS_APROVADA => 'success',
                        PropostaComercial::STATUS_REJEITADA => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PropostaComercial::statusLabels()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropostaComerciais::route('/'),
            'view' => Pages\ViewPropostaComercial::route('/{record}'),
        ];
    }
}
