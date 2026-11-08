<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CkExecutionResource\Pages;
use App\Models\AssetCategory;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Models\User;
use App\Support\CkExecucaoExport;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Relatorio de checklists executados (C4): somente leitura, com filtros por periodo, grupo, executor, resultado, checklist e origem,
 * detalhe com respostas e fotos, e exportacao Excel do que esta na tela. Inclui o que foi copiado da Frota. Acesso pelo
 * CkExecutionPolicy (chave tabela_ck_execucoes + permissao ler_execucao_checklist; admin do tenant tem bypass).
 */
class CkExecutionResource extends BaseResource
{
    protected static ?string $model = CkExecution::class;

    protected static ?string $slug = 'checklists-execucoes';

    protected static ?string $modelLabel = 'Execução de Checklist';

    protected static ?string $pluralModelLabel = 'Execuções de Checklist';

    protected static ?string $navigationLabel = 'Checklists de equipamento';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationParentItem = 'Históricos & Logs';

    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && Gate::allows('viewAny', CkExecution::class);
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view', $record);
    }

    public static function canCreate(): bool
    {
        return false; // a execucao nasce na tela mobile (QR)
    }

    public static function canEdit(Model $record): bool
    {
        return false; // historico imutavel
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['asset.category', 'executor', 'ordemServico'])->withCount([
            'respostas as criticos_nc_count' => fn ($q) => $q->where('critico', true)->where('resposta', 'nao_conforme'),
            'respostas as pendencias_count' => fn ($q) => $q->where('critico', false)->where('resposta', 'nao_conforme'),
        ]);
    }

    private static function tenantId(): ?string
    {
        return auth()->user()?->tenant_id ?? Tenancy::current()?->id;
    }

    private static function corStatus(?string $s): string
    {
        return match ($s) {
            CkExecution::STATUS_APROVADO => 'success', CkExecution::STATUS_APROVADO_COM_RESSALVA => 'warning', CkExecution::STATUS_REPROVADO => 'danger', default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('finalizado_em', 'desc')
            ->recordUrl(fn (CkExecution $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                Tables\Columns\TextColumn::make('finalizado_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('asset.category.name')->label('Grupo')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('template_nome')->label('Checklist')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('versao_template')->label('Versão')->prefix('v')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('executor.name')->label('Executor')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Resultado')->badge()
                    ->formatStateUsing(fn (?string $state) => CkExecucaoExport::STATUS[$state] ?? $state)->color(fn (?string $state) => static::corStatus($state)),
                Tables\Columns\TextColumn::make('criticos_nc_count')->label('Críticos')->alignEnd()->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('pendencias_count')->label('Pendências')->alignEnd()->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('ordemServico.os_number')->label('OS')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('legado_tipo')->label('Origem')->badge()->formatStateUsing(fn (?string $state) => $state ? 'Frota' : 'Checklist')->color(fn (?string $state) => $state ? 'gray' : 'primary')->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['de'] ?? null, fn (Builder $q, $d) => $q->whereDate('finalizado_em', '>=', $d))
                        ->when($data['ate'] ?? null, fn (Builder $q, $d) => $q->whereDate('finalizado_em', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        return array_filter([
                            ($data['de'] ?? null) ? 'A partir de '.date('d/m/Y', strtotime($data['de'])) : null,
                            ($data['ate'] ?? null) ? 'Até '.date('d/m/Y', strtotime($data['ate'])) : null,
                        ]);
                    }),
                Tables\Filters\SelectFilter::make('grupo')->label('Grupo de equipamento')
                    ->options(fn () => AssetCategory::query()->where('tenant_id', static::tenantId())->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $v) => $q->whereHas('asset', fn ($a) => $a->where('asset_category_id', $v)))),
                Tables\Filters\SelectFilter::make('executor_id')->label('Executor')
                    ->options(fn () => User::query()->where('tenant_id', static::tenantId())->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('status')->label('Resultado')->options(CkExecucaoExport::STATUS),
                Tables\Filters\SelectFilter::make('template_id')->label('Checklist')
                    ->options(fn () => CkTemplate::query()->where('tenant_id', static::tenantId())->orderBy('nome')->pluck('nome', 'id')->all()),
                Tables\Filters\SelectFilter::make('origem')->label('Origem')->options(['checklist' => 'Checklist', 'frota' => 'Frota'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'checklist' => $query->whereNull('legado_tipo'), 'frota' => $query->whereNotNull('legado_tipo'), default => $query,
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Resumo')->schema([
                Infolists\Components\TextEntry::make('asset.name')->label('Ativo'),
                Infolists\Components\TextEntry::make('template_nome')->label('Checklist'),
                Infolists\Components\TextEntry::make('versao_template')->label('Versão')->prefix('v'),
                Infolists\Components\TextEntry::make('executor.name')->label('Executor')->placeholder('—'),
                Infolists\Components\TextEntry::make('finalizado_em')->label('Finalizado em')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('status')->label('Resultado')->badge()
                    ->formatStateUsing(fn (?string $state) => CkExecucaoExport::STATUS[$state] ?? $state)->color(fn (?string $state) => static::corStatus($state)),
                Infolists\Components\TextEntry::make('leitura_medidor')->label('Leitura (h/km)')->placeholder('—'),
                Infolists\Components\TextEntry::make('ordemServico.os_number')->label('OS aberta')->placeholder('—'),
                Infolists\Components\TextEntry::make('observacoes')->label('Observações')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
            Infolists\Components\Section::make('Respostas')->schema([
                Infolists\Components\RepeatableEntry::make('respostas')->label('')->schema([
                    Infolists\Components\TextEntry::make('secao_titulo')->label('Seção')->placeholder('—'),
                    Infolists\Components\TextEntry::make('item_descricao')->label('Item')->columnSpan(2),
                    Infolists\Components\TextEntry::make('resposta')->label('Resposta')->badge()->placeholder('—')
                        ->formatStateUsing(fn (?string $state) => match ($state) {
                            'conforme' => 'Conforme', 'nao_conforme' => 'Não conforme', 'na' => 'Não se aplica', default => $state
                        })
                        ->color(fn (?string $state) => match ($state) {
                            'conforme' => 'success', 'nao_conforme' => 'danger', default => 'gray'
                        }),
                    Infolists\Components\TextEntry::make('valor')->label('Valor')->state(fn ($record) => $record->valor_numerico !== null ? rtrim(rtrim(number_format((float) $record->valor_numerico, 3, ',', '.'), '0'), ',') : $record->valor_texto)->placeholder('—'),
                    Infolists\Components\IconEntry::make('critico')->label('Crítico')->boolean(),
                    Infolists\Components\TextEntry::make('observacao')->label('Observação')->placeholder('—')->columnSpan(2),
                    Infolists\Components\TextEntry::make('fotos')->label('Fotos')->html()->placeholder('—')
                        ->state(fn ($record) => collect((array) $record->fotos)->map(fn ($c, $i) => '<a href="'.e(route('ck.resposta.foto', [$record->id, $i])).'" target="_blank" rel="noopener" class="underline">Ver foto '.($i + 1).'</a>')->implode(' · ') ?: null),
                ])->columns(3),
            ]),
            Infolists\Components\Section::make('Assinatura')->schema([
                Infolists\Components\TextEntry::make('assinatura')->label('')->html()->placeholder('Sem assinatura')
                    ->state(fn (CkExecution $record) => str_starts_with((string) $record->assinatura, 'data:image') ? new HtmlString('<img alt="Assinatura" src="'.e($record->assinatura).'" style="max-height:110px;border-radius:8px;background:#e5e7eb">') : null),
            ])->collapsed(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCkExecutions::route('/'),
            'view' => Pages\ViewCkExecution::route('/{record}'),
        ];
    }
}
