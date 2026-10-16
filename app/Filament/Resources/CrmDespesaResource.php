<?php

namespace App\Filament\Resources;

use App\Filament\Exports\CrmDespesaExporter;
use App\Filament\Resources\CrmDespesaResource\Pages;
use App\Models\CrmVeiculo;
use App\Models\CrmVisita;
use App\Models\CrmVisitaDespesa;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Despesas de campo: lançamento, comprovante privado, aprovação e acerto por vendedor/período. */
class CrmDespesaResource extends BaseResource
{
    protected static ?string $model = CrmVisitaDespesa::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Despesas de Visita';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 13;

    protected static ?string $modelLabel = 'Despesa';

    protected static ?string $pluralModelLabel = 'Despesas de Visita';

    /** Vendedor sem "ver todos" só enxerga as próprias despesas. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->canSeeAllCrmLeads()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Despesa')->columns(3)->schema([
                Forms\Components\DatePicker::make('data')->label('Data')->default(now())->required(),
                Forms\Components\Select::make('tipo')->label('Tipo')->options(CrmVisitaDespesa::tipoLabels())->default('combustivel')->required()->live(),
                Forms\Components\TextInput::make('valor')->label('Valor (R$)')->numeric()->prefix('R$')->minValue(0.01)->required(),
                Forms\Components\TextInput::make('litros')->label('Litros')->numeric()->minValue(0)
                    ->visible(fn (Forms\Get $get) => $get('tipo') === 'combustivel'),
                Forms\Components\Select::make('crm_veiculo_id')->label('Veículo')->searchable()
                    ->options(fn () => CrmVeiculo::orderBy('placa')->get()->mapWithKeys(fn ($v) => [$v->id => $v->rotulo()])),
                Forms\Components\Select::make('crm_visita_id')->label('Visita (opcional)')->searchable()
                    ->options(fn () => CrmVisitaResource::getEloquentQuery()->latest('agendada_para')->limit(100)->get()
                        ->mapWithKeys(fn (CrmVisita $v) => [$v->id => $v->agendada_para?->format('d/m H:i').' — '.$v->alvoNome()])),
                Forms\Components\TextInput::make('descricao')->label('Descrição')->maxLength(255)->columnSpanFull(),
                Forms\Components\SpatieMediaLibraryFileUpload::make('comprovante')->label('Comprovante (foto ou PDF)')
                    ->collection('comprovante')->disk('media_private')->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(8192)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('vendedor.name')->label('Vendedor')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => CrmVisitaDespesa::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->sortable()->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Veículo')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => CrmVisitaDespesa::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'aprovada' => 'info', 'paga' => 'success', 'rejeitada' => 'danger', default => 'gray'
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(CrmVisitaDespesa::statusLabels()),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(CrmVisitaDespesa::tipoLabels()),
                Tables\Filters\SelectFilter::make('user_id')->label('Vendedor')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn () => (bool) auth()->user()?->canSeeAllCrmLeads()),
                Tables\Filters\Filter::make('periodo')->label('Período')
                    ->form([Forms\Components\DatePicker::make('de')->label('De'), Forms\Components\DatePicker::make('ate')->label('Até')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['de'] ?? null, fn ($qq, $v) => $qq->whereDate('data', '>=', $v))
                        ->when($data['ate'] ?? null, fn ($qq, $v) => $qq->whereDate('data', '<=', $v))),
            ])
            ->actions([
                Tables\Actions\Action::make('comprovante')->label('Comprovante')->icon('heroicon-o-paper-clip')->color('gray')
                    ->visible(fn (CrmVisitaDespesa $r) => $r->hasMedia('comprovante'))
                    ->url(fn (CrmVisitaDespesa $r) => $r->getFirstMedia('comprovante')?->getTemporaryUrl(now()->addMinutes(10)))
                    ->openUrlInNewTab(),
                Tables\Actions\ActionGroup::make([
                    static::acaoDecisao('aprovar', 'Aprovar', CrmVisitaDespesa::STATUS_APROVADA, 'heroicon-o-check', 'info', [CrmVisitaDespesa::STATUS_PENDENTE, CrmVisitaDespesa::STATUS_REJEITADA]),
                    static::acaoDecisao('rejeitar', 'Rejeitar', CrmVisitaDespesa::STATUS_REJEITADA, 'heroicon-o-x-mark', 'danger', [CrmVisitaDespesa::STATUS_PENDENTE, CrmVisitaDespesa::STATUS_APROVADA]),
                    static::acaoDecisao('pagar', 'Marcar como paga', CrmVisitaDespesa::STATUS_PAGA, 'heroicon-o-banknotes', 'success', [CrmVisitaDespesa::STATUS_APROVADA]),
                ])->label('Decidir')->visible(fn () => (bool) auth()->user()?->canSeeAllCrmLeads()),
                Tables\Actions\EditAction::make()->visible(fn (CrmVisitaDespesa $r) => $r->status === CrmVisitaDespesa::STATUS_PENDENTE || auth()->user()?->canSeeAllCrmLeads()),
            ])
            ->headerActions([
                ExportAction::make()->exporter(CrmDespesaExporter::class)->label('Exportar')->color('gray'),
                Tables\Actions\Action::make('pdf')->label('PDF de acerto')->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De')->default(now()->startOfMonth())->required(),
                        Forms\Components\DatePicker::make('ate')->label('Até')->default(now())->required(),
                        Forms\Components\Select::make('user_id')->label('Vendedor')->placeholder('Todos')
                            ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                            ->visible(fn () => (bool) auth()->user()?->canSeeAllCrmLeads()),
                    ])
                    ->action(fn (array $data) => redirect()->route('crm.despesas.pdf', array_filter($data))),
            ]);
    }

    private static function acaoDecisao(string $nome, string $rotulo, string $status, string $icone, string $cor, array $de): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($nome)->label($rotulo)->icon($icone)->color($cor)
            ->visible(fn (CrmVisitaDespesa $r) => in_array($r->status, $de, true))
            ->requiresConfirmation()
            ->action(function (CrmVisitaDespesa $r) use ($status, $rotulo) {
                $r->decidir($status, auth()->user());
                Notification::make()->title("Despesa: {$rotulo}.")->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmDespesas::route('/'),
            'create' => Pages\CreateCrmDespesa::route('/create'),
            'edit' => Pages\EditCrmDespesa::route('/{record}/edit'),
        ];
    }
}
