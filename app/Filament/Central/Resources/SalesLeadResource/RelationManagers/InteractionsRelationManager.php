<?php

namespace App\Filament\Central\Resources\SalesLeadResource\RelationManagers;

use App\Models\SalesLeadInteraction;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aba "Follow Up" -- registro de contato com o lead. Alem do fluxo
 * tradicional (botao "Registrar Interação" abrindo modal), tem uma caixa
 * de registro rapido sempre visivel no topo (submete com Enter, data/hora
 * automatica) -- pedido explicito do usuario. View customizada
 * (interactions-relation-manager.blade.php) injeta essa caixa antes da
 * tabela padrao do Filament.
 */
class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Follow Up';

    protected static string $view = 'filament.central.resources.sales-lead-resource.relation-managers.interactions-relation-manager';

    public string $quickNote = '';

    public function addQuickNote(): void
    {
        $note = trim($this->quickNote);

        if ($note === '') {
            return;
        }

        $this->getOwnerRecord()->interactions()->create([
            'contact_date' => now(),
            'channel' => SalesLeadInteraction::CHANNEL_OUTRO,
            'summary' => $note,
            'user_id' => auth()->id(),
            'stage_at_time' => $this->getOwnerRecord()->pipeline_stage,
        ]);

        $this->quickNote = '';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('contact_date')
                ->label('Data do Contato')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('channel')
                ->label('Canal')
                ->options(SalesLeadInteraction::channelLabels())
                ->required(),
            Forms\Components\Textarea::make('summary')
                ->label('Resumo do Contato')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                // Stack (nao colunas lado a lado) de proposito -- o texto do
                // follow up precisa ter a largura toda do quadro, pedido
                // explicito do usuario, o que so' da' pra garantir com cada
                // entrada ocupando a linha inteira, nao dividindo espaco
                // horizontal com data/canal/autor.
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('contact_date')
                        ->label('Data')
                        ->dateTime('d/m/Y H:i')
                        ->weight('bold')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('channel')
                        ->label('Canal')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => SalesLeadInteraction::channelLabels()[$state] ?? $state),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('Registrado por')
                        ->color('gray')
                        ->size('xs'),
                    // Sem limit() de proposito -- largura total do quadro,
                    // bastante espaco pra ler o texto inteiro.
                    Tables\Columns\TextColumn::make('summary')
                        ->label('Anotação')
                        ->wrap()
                        ->extraAttributes(['class' => 'text-sm leading-relaxed py-1']),
                ])->space(2),
            ])
            ->defaultSort('contact_date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('contact_date')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('contact_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('contact_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'De '.Carbon::parse($data['from'])->format('d/m/Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Até '.Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->persistFiltersInSession()
            ->filtersFormColumns(2)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Interação (com canal)')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['stage_at_time'] = $this->getOwnerRecord()->pipeline_stage;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
