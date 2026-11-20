<?php

namespace App\Relacionamento\Filament\Resources;

use App\Models\Prospect;
use App\Models\SalesLead;
use App\Relacionamento\Filament\RelationManagers\InteracoesDoContatoRelationManager;
use App\Relacionamento\Filament\Resources\RelContatoResource\Pages\ListRelContatos;
use App\Relacionamento\Filament\Resources\RelContatoResource\Pages\ViewRelContato;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Services\CalculaScore;
use App\Relacionamento\Services\ProximaAcao;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Pessoas que se identificaram no ecossistema Oravel (assinantes da Gazeta, quem pediu material...), com ou sem
 * empresa vinculada. E' aqui que a equipe ve quem quer receber conteudo e decide o que fazer com cada um.
 * So' super admin.
 */
class RelContatoResource extends Resource
{
    protected static ?string $model = RelContato::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Relacionamento Oravel';

    protected static ?string $navigationLabel = 'Contatos';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'contato';

    protected static ?string $pluralModelLabel = 'Contatos (assinantes e visitantes)';

    protected static ?string $recordTitleAttribute = 'email';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function score(RelContato $c): array
    {
        static $calc = null;
        $calc ??= app(CalculaScore::class);

        return $calc->calcular($c->interacoes());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['prospect:id,nome', 'salesLead:id,company_name'])->withMax('interacoes', 'ocorrido_em')->withCount('interacoes'))
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Nome')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->copyable()->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('telefone')->label('Telefone')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('empresa_nome')->label('Empresa (informada)')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('empresa_vinculada')->label('Empresa na Central')
                    ->state(fn (RelContato $c): ?string => $c->prospect?->nome ?? $c->salesLead?->company_name)
                    ->badge()->color(fn (?string $state): string => $state ? 'success' : 'warning')->placeholder('Sem vínculo'),
                Tables\Columns\TextColumn::make('relacionamento')->label('Relacionamento')
                    ->state(function (RelContato $c): string {
                        $s = self::score($c);

                        return "{$s['score']}/100 · {$s['faixa']}";
                    })
                    ->badge()->color(fn (RelContato $c): string => self::score($c)['cor']),
                Tables\Columns\TextColumn::make('interacoes_count')->label('Interações')->sortable(),
                Tables\Columns\TextColumn::make('interacoes_max_ocorrido_em')->label('Última interação')->dateTime('d/m/Y H:i')->sortable()->placeholder('—'),
                Tables\Columns\IconColumn::make('consentimento_em')->label('Aceitou receber')->boolean()
                    ->getStateUsing(fn (RelContato $c): bool => $c->consentimento_em !== null && $c->descadastrado_em === null)
                    ->tooltip(fn (RelContato $c): ?string => $c->descadastrado_em
                        ? 'Descadastrou-se em '.$c->descadastrado_em->format('d/m/Y')
                        : ($c->consentimento_em ? $c->consentimento_origem.' · '.$c->consentimento_em->format('d/m/Y H:i') : 'Sem registro de consentimento')),
                Tables\Columns\TextColumn::make('created_at')->label('Entrou em')->dateTime('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('interacoes_max_ocorrido_em', 'desc')
            ->filters([
                Tables\Filters\Filter::make('sem_empresa')->label('Sem empresa vinculada')->toggle()
                    ->query(fn (Builder $query) => $query->whereNull('prospect_id')->whereNull('sales_lead_id')),
                Tables\Filters\Filter::make('com_consentimento')->label('Aceitaram receber conteúdo')->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('consentimento_em')->whereNull('descadastrado_em')),
                Tables\Filters\Filter::make('descadastrados')->label('Descadastrados (não contatar)')->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('descadastrado_em')),
                Tables\Filters\SelectFilter::make('consentimento_origem')->label('Origem do aceite')
                    ->options(fn () => RelContato::whereNotNull('consentimento_origem')->distinct()->pluck('consentimento_origem', 'consentimento_origem')->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Abrir'),
                Tables\Actions\Action::make('vincular')->label('Vincular a empresa')->icon('heroicon-o-link')
                    ->modalHeading('Vincular a uma empresa da Central')
                    ->modalDescription('Todo o histórico desta pessoa passa a aparecer na ficha da empresa escolhida.')
                    ->form([
                        Forms\Components\Select::make('prospect_id')->label('Prospect')->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Prospect::busca($search)->limit(30)->get()->mapWithKeys(fn (Prospect $p) => [$p->id => $p->nome.($p->cidade ? " · {$p->cidade}" : '')])->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Prospect::find($value)?->nome),
                        Forms\Components\Select::make('sales_lead_id')->label('Lead do CRM Comercial')->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => SalesLead::whereRaw('LOWER(company_name) LIKE ?', ['%'.mb_strtolower($search).'%'])->limit(30)->pluck('company_name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => SalesLead::find($value)?->company_name)
                            ->helperText('Se escolher um prospect que já virou lead, o lead é ligado automaticamente.'),
                    ])
                    ->action(function (RelContato $record, array $data): void {
                        $prospect = filled($data['prospect_id'] ?? null) ? Prospect::find($data['prospect_id']) : null;
                        $leadId = $data['sales_lead_id'] ?? $prospect?->sales_lead_id;

                        if (! $prospect && blank($leadId)) {
                            Notification::make()->title('Escolha uma empresa')->warning()->send();

                            return;
                        }

                        DB::transaction(function () use ($record, $prospect, $leadId) {
                            $record->update(['prospect_id' => $prospect?->id, 'sales_lead_id' => $leadId]);
                            $record->interacoes()->update(['prospect_id' => $prospect?->id, 'sales_lead_id' => $leadId]);
                        });
                        Notification::make()->title('Contato vinculado')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Excluir contato e histórico')
                    ->modalDescription('Apaga a pessoa e TODO o histórico dela (direito de exclusão da LGPD). Não dá para desfazer.')
                    ->before(fn (RelContato $record) => $record->interacoes()->delete()),
            ])
            ->emptyStateHeading('Nenhum contato ainda')
            ->emptyStateDescription('Quem assinar a Gazeta ou pedir um material no oravel.org aparece aqui.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Contato')->schema([
                Infolists\Components\TextEntry::make('nome')->placeholder('—'),
                Infolists\Components\TextEntry::make('email')->copyable()->placeholder('—'),
                Infolists\Components\TextEntry::make('telefone')->placeholder('—'),
                Infolists\Components\TextEntry::make('empresa_nome')->label('Empresa (informada)')->placeholder('—'),
                Infolists\Components\TextEntry::make('empresa_vinculada')->label('Empresa na Central')
                    ->state(fn (RelContato $c): string => $c->prospect?->nome ?? $c->salesLead?->company_name ?? 'Sem vínculo'),
                Infolists\Components\TextEntry::make('proxima_acao')->label('Próxima ação sugerida')
                    ->state(fn (RelContato $c): string => app(ProximaAcao::class)->para(self::score($c)['score'])?->titulo ?? '—'),
                Infolists\Components\TextEntry::make('consentimento')->label('Consentimento')
                    ->state(fn (RelContato $c): string => $c->descadastrado_em
                        ? 'DESCADASTRADO em '.$c->descadastrado_em->format('d/m/Y').' — não contatar'
                        : ($c->consentimento_em ? $c->consentimento_origem.' em '.$c->consentimento_em->format('d/m/Y H:i') : 'Sem registro de consentimento')),
            ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [InteracoesDoContatoRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelContatos::route('/'),
            'view' => ViewRelContato::route('/{record}'),
        ];
    }
}
