<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmPropostaResource\Pages;
use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Models\CrmProposta;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Propostas do CRM (Conta + catálogo), com PDF, envio por e-mail/WhatsApp e aceite online. */
class CrmPropostaResource extends BaseResource
{
    protected static ?string $model = CrmProposta::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Propostas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Proposta';

    protected static ?string $pluralModelLabel = 'Propostas';

    /** Vendedor sem "ver todos" só enxerga as próprias propostas. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->canSeeAllCrmLeads()) {
            $query->where('vendedor_user_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Proposta')->columns(3)->schema([
                Forms\Components\TextInput::make('titulo')->label('Título')->required()->maxLength(255)->columnSpan(2),
                Forms\Components\DatePicker::make('validade_ate')->label('Válida até')->default(now()->addDays(15)),
                Forms\Components\Select::make('crm_conta_id')->label('Conta (cliente)')->searchable()
                    ->options(fn () => CrmConta::orderBy('nome')->pluck('nome', 'id')),
                Forms\Components\Select::make('crm_lead_id')->label('Lead')->searchable()
                    ->options(fn () => CrmLead::orderBy('name')->get()->mapWithKeys(fn ($l) => [$l->id => $l->company_name ? "{$l->name} — {$l->company_name}" : $l->name])),
                Forms\Components\Select::make('vendedor_user_id')->label('Vendedor')->searchable()
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->disabled(fn () => ! auth()->user()?->canSeeAllCrmLeads())->dehydrated(),
            ])->disabled(fn (?CrmProposta $record) => $record && ! $record->editavel()),
            Forms\Components\Section::make('Condições')->columns(2)->schema([
                Forms\Components\Textarea::make('condicoes')->label('Condições (pagamento, prazo, garantia)')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('desconto')->label('Desconto (R$)')->numeric()->prefix('R$')->minValue(0)->default(0),
                Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
            ])->disabled(fn (?CrmProposta $record) => $record && ! $record->editavel()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('numero', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Nº')->sortable(),
                Tables\Columns\TextColumn::make('titulo')->label('Proposta')->searchable()->description(fn (CrmProposta $r) => $r->destinatarioNome()),
                Tables\Columns\TextColumn::make('vendedor.name')->label('Vendedor')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('validade_ate')->label('Validade')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => CrmProposta::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'aceita' => 'success', 'enviada' => 'info', 'recusada' => 'danger', 'expirada' => 'warning', default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('visualizada')->label('Vista')->boolean()->getStateUsing(fn (CrmProposta $r) => (bool) $r->visualizada_em)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(CrmProposta::statusLabels()),
                Tables\Filters\SelectFilter::make('vendedor_user_id')->label('Vendedor')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn () => (bool) auth()->user()?->canSeeAllCrmLeads()),
                Tables\Filters\Filter::make('periodo')->label('Criada no período')
                    ->form([Forms\Components\DatePicker::make('de')->label('De'), Forms\Components\DatePicker::make('ate')->label('Até')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['de'] ?? null, fn ($qq, $v) => $qq->whereDate('created_at', '>=', $v))
                        ->when($data['ate'] ?? null, fn ($qq, $v) => $qq->whereDate('created_at', '<=', $v))),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('pdf')->label('Baixar PDF')->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (CrmProposta $r) => route('crm.propostas.pdf', $r))->openUrlInNewTab(),
                    Tables\Actions\Action::make('enviarEmail')->label('Enviar por e-mail')->icon('heroicon-o-envelope')
                        ->requiresConfirmation()
                        ->visible(fn (CrmProposta $r) => in_array($r->status, [CrmProposta::STATUS_RASCUNHO, CrmProposta::STATUS_ENVIADA], true))
                        ->action(fn (CrmProposta $r) => static::executar(fn () => $r->enviarPorEmail(auth()->user()), 'Proposta enviada por e-mail.')),
                    Tables\Actions\Action::make('whatsapp')->label('Enviar por WhatsApp')->icon('heroicon-o-chat-bubble-left-right')
                        ->visible(fn (CrmProposta $r) => $r->linkWhatsapp() !== null)
                        ->url(fn (CrmProposta $r) => $r->linkWhatsapp())->openUrlInNewTab()
                        ->after(fn (CrmProposta $r) => $r->marcarEnviada()),
                    Tables\Actions\Action::make('aceitarManual')->label('Marcar como aceita')->icon('heroicon-o-check-badge')->color('success')
                        ->visible(fn (CrmProposta $r) => in_array($r->status, [CrmProposta::STATUS_RASCUNHO, CrmProposta::STATUS_ENVIADA], true))
                        ->form([Forms\Components\TextInput::make('nome')->label('Quem aceitou')->required()->minLength(3)])
                        ->action(fn (CrmProposta $r, array $data) => static::executar(fn () => $r->marcarAceitaManualmente($data['nome']), 'Proposta aceita; o lead foi fechado como ganho.')),
                    Tables\Actions\Action::make('duplicar')->label('Duplicar')->icon('heroicon-o-document-duplicate')
                        ->action(fn (CrmProposta $r) => static::executar(fn () => $r->duplicar(auth()->user()), 'Cópia criada como rascunho.')),
                    Tables\Actions\Action::make('reabrir')->label('Reabrir como rascunho')->icon('heroicon-o-arrow-uturn-left')
                        ->visible(fn (CrmProposta $r) => in_array($r->status, [CrmProposta::STATUS_RECUSADA, CrmProposta::STATUS_EXPIRADA], true))
                        ->action(fn (CrmProposta $r) => static::executar(fn () => $r->reabrir(), 'Proposta reaberta como rascunho.')),
                ]),
                Tables\Actions\EditAction::make(),
            ]);
    }

    /** Roda uma ação do modelo traduzindo as falhas de regra em avisos na tela. */
    public static function executar(\Closure $acao, string $sucesso): void
    {
        try {
            $acao();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($sucesso)->success()->send();
    }

    public static function getRelations(): array
    {
        return [CrmPropostaResource\RelationManagers\ItensRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmPropostas::route('/'),
            'create' => Pages\CreateCrmProposta::route('/create'),
            'edit' => Pages\EditCrmProposta::route('/{record}/edit'),
        ];
    }
}
