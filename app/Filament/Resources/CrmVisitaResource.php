<?php

namespace App\Filament\Resources;

use App\Filament\Exports\CrmVisitaExporter;
use App\Filament\Resources\CrmVisitaResource\Pages;
use App\Filament\Resources\CrmVisitaResource\RelationManagers\DespesasRelationManager;
use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Models\CrmVeiculo;
use App\Models\CrmVisita;
use App\Models\User;
use App\Services\CrmVisitaService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Visitas de campo dos vendedores: agenda, check-in/out, km e resultado. */
class CrmVisitaResource extends BaseResource
{
    protected static ?string $model = CrmVisita::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Visitas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Visita';

    protected static ?string $pluralModelLabel = 'Visitas';

    /** Vendedor sem "ver todos" só enxerga as próprias visitas. */
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
            Forms\Components\Section::make('Visita')->columns(3)->schema([
                Forms\Components\Select::make('user_id')->label('Vendedor')->required()->searchable()
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->disabled(fn () => ! auth()->user()?->canSeeAllCrmLeads())->dehydrated(),
                Forms\Components\Select::make('crm_conta_id')->label('Conta (cliente)')->searchable()->preload()
                    ->options(fn () => CrmConta::orderBy('nome')->pluck('nome', 'id')),
                Forms\Components\Select::make('crm_lead_id')->label('Lead')->searchable()
                    ->options(fn () => CrmLead::orderBy('name')->get()->mapWithKeys(fn ($l) => [$l->id => $l->company_name ? "{$l->name} — {$l->company_name}" : $l->name])),
                Forms\Components\TextInput::make('contato_nome')->label('Pessoa a visitar')->maxLength(255),
                Forms\Components\Select::make('objetivo')->label('Objetivo')->options(CrmVisita::objetivoLabels())->default('apresentacao')->required(),
                Forms\Components\DateTimePicker::make('agendada_para')->label('Data e hora')->required()->seconds(false),
                Forms\Components\TextInput::make('duracao_prevista_min')->label('Duração prevista (min)')->numeric()->minValue(5)->default(60),
                Forms\Components\Select::make('crm_veiculo_id')->label('Veículo')->searchable()
                    ->options(fn () => CrmVeiculo::where('ativo', true)->orderBy('placa')->get()->mapWithKeys(fn ($v) => [$v->id => $v->rotulo()])),
                Forms\Components\Select::make('status')->label('Status')->options(CrmVisita::statusLabels())->default('agendada')->required(),
            ]),
            Forms\Components\Section::make('Endereço')->columns(3)->collapsible()->schema([
                Forms\Components\TextInput::make('endereco')->label('Endereço')->columnSpan(2)->helperText('Vazio = usa o endereço do lead/conta.'),
                Forms\Components\TextInput::make('cidade')->label('Cidade'),
                Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                Forms\Components\TextInput::make('latitude')->label('Latitude')->numeric(),
                Forms\Components\TextInput::make('longitude')->label('Longitude')->numeric(),
            ]),
            Forms\Components\Section::make('Resultado')->columns(3)->collapsible()->schema([
                Forms\Components\TextInput::make('km_inicio')->label('Km inicial')->numeric()->minValue(0),
                Forms\Components\TextInput::make('km_fim')->label('Km final')->numeric()->minValue(0)
                    ->gte('km_inicio'),
                Forms\Components\DatePicker::make('proximo_contato')->label('Próximo contato'),
                Forms\Components\Textarea::make('resultado')->label('Resultado da visita')->columnSpan(2)->rows(2),
                Forms\Components\Textarea::make('proximo_passo')->label('Próximo passo')->rows(2),
                Forms\Components\Textarea::make('observacoes')->label('Observações')->columnSpanFull()->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('agendada_para', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('agendada_para')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('vendedor.name')->label('Vendedor')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('alvo')->label('Visita a')->state(fn (CrmVisita $r) => $r->alvoNome())->description(fn (CrmVisita $r) => trim(($r->cidade ?? '').' '.($r->uf ?? ''))),
                Tables\Columns\TextColumn::make('objetivo')->label('Objetivo')->formatStateUsing(fn ($state) => CrmVisita::objetivoLabels()[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => CrmVisita::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        CrmVisita::STATUS_REALIZADA => 'success', CrmVisita::STATUS_EM_ANDAMENTO => 'warning',
                        CrmVisita::STATUS_CANCELADA, CrmVisita::STATUS_NAO_ENCONTROU => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Veículo')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('km')->label('Km rodados')->state(fn (CrmVisita $r) => $r->kmRodados())->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('duracao')->label('Duração (min)')->state(fn (CrmVisita $r) => $r->duracaoMin())->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(CrmVisita::statusLabels()),
                Tables\Filters\SelectFilter::make('user_id')->label('Vendedor')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn () => (bool) auth()->user()?->canSeeAllCrmLeads()),
                Tables\Filters\Filter::make('periodo')->label('Período')
                    ->form([Forms\Components\DatePicker::make('de')->label('De'), Forms\Components\DatePicker::make('ate')->label('Até')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['de'] ?? null, fn ($qq, $v) => $qq->whereDate('agendada_para', '>=', $v))
                        ->when($data['ate'] ?? null, fn ($qq, $v) => $qq->whereDate('agendada_para', '<=', $v))),
            ])
            ->actions([
                Tables\Actions\Action::make('checkin')->label('Check-in')->icon('heroicon-o-arrow-right-end-on-rectangle')->color('warning')
                    ->visible(fn (CrmVisita $r) => $r->status === CrmVisita::STATUS_AGENDADA)
                    ->action(fn (CrmVisita $r) => static::executar(fn () => app(CrmVisitaService::class)->checkin($r, auth()->user()), 'Check-in registrado (sem localização; use o celular para registrar a posição).')),
                Tables\Actions\Action::make('finalizar')->label('Finalizar')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (CrmVisita $r) => $r->status === CrmVisita::STATUS_EM_ANDAMENTO)
                    ->form([
                        Forms\Components\Textarea::make('resultado')->label('Resultado')->required(),
                        Forms\Components\TextInput::make('km_fim')->label('Km final')->numeric(),
                        Forms\Components\Textarea::make('proximo_passo')->label('Próximo passo'),
                        Forms\Components\DatePicker::make('proximo_contato')->label('Próximo contato'),
                    ])
                    ->action(fn (CrmVisita $r, array $data) => static::executar(fn () => app(CrmVisitaService::class)->checkout($r, auth()->user(), $data), 'Visita finalizada.')),
                Tables\Actions\Action::make('duplicar')->label('Reagendar')->icon('heroicon-o-document-duplicate')->color('gray')
                    ->form([Forms\Components\DateTimePicker::make('agendada_para')->label('Nova data e hora')->required()->seconds(false)])
                    ->action(function (CrmVisita $r, array $data) {
                        $copia = $r->replicate(['status', 'checkin_em', 'checkin_lat', 'checkin_lng', 'checkin_distancia_m', 'checkout_em', 'checkout_lat', 'checkout_lng', 'km_inicio', 'km_fim', 'resultado', 'proximo_passo', 'proximo_contato', 'ordem_roteiro']);
                        $copia->fill(['agendada_para' => $data['agendada_para'], 'status' => CrmVisita::STATUS_AGENDADA])->save();
                        Notification::make()->title('Nova visita agendada.')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()->exporter(CrmVisitaExporter::class)->label('Exportar')->color('gray'),
            ]);
    }

    /** Roda uma ação do serviço traduzindo as falhas de regra em avisos na tela. */
    public static function executar(\Closure $acao, string $sucesso): void
    {
        try {
            $r = $acao();
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title($sucesso)->success()->send();

        foreach ((is_array($r) ? ($r['avisos'] ?? []) : []) as $aviso) {
            Notification::make()->title($aviso)->warning()->send();
        }
    }

    public static function getRelations(): array
    {
        return [DespesasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmVisitas::route('/'),
            'create' => Pages\CreateCrmVisita::route('/create'),
            'edit' => Pages\EditCrmVisita::route('/{record}/edit'),
        ];
    }
}
