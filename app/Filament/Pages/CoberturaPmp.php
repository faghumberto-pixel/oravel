<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Filament\Actions\Action as PageAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Dá visibilidade ao vínculo Plano de Manutenção <-> Ativo (já existente,
 * MaintenancePlan::applicableFor()) que hoje só aparecia como texto
 * informativo dentro do form de uma OS já criada (ver
 * MaintenanceOrderResource.php, Placeholder "Preventivas Sugeridas").
 * Ver docs/superpowers/specs/2026-08-29-cobertura-pmp-design.md.
 */
class CoberturaPmp extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $navigationLabel = 'Cobertura de PMP';

    protected static ?string $title = 'Cobertura de Manutenção Preventiva';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.cobertura-pmp';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * Reaproveita a mesma infraestrutura de App\Filament\Concerns\HasPrintAction
     * (rota table-print.show, cache por token, view reports.table-print) sem
     * usar o trait em si -- ele assume static::getResource() (só existe em
     * Resource List Pages), e CoberturaPmp é uma Page avulsa. As colunas do
     * relatório também são próprias desta tela (Status PMP, Grupo, Próximo
     * Vencimento) em vez das colunas genéricas de Asset compartilhadas pelas
     * outras 27 telas que já imprimem Ativos.
     */
    protected function getHeaderActions(): array
    {
        return [
            PageAction::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(function () {
                    $query = $this->getFilteredSortedTableQuery();
                    $ids = $query->pluck('id')->all();

                    $filtros = collect($this->getTable()->getFilters())
                        ->mapWithKeys(fn ($filter) => [$filter->getName() => $filter->getLabel()])
                        ->filter(fn ($label, $name) => filled($this->tableFilters[$name] ?? null))
                        ->map(function ($label, $name) {
                            $value = $this->tableFilters[$name];
                            $raw = is_array($value) ? ($value['value'] ?? reset($value)) : $value;

                            if ($name === 'pmp_status') {
                                $raw = static::statusLabel((string) $raw);
                            }

                            return "{$label}: {$raw}";
                        })
                        ->values()
                        ->all();

                    $token = (string) Str::uuid();

                    Cache::put("table-print:{$token}", [
                        'model' => Asset::class,
                        'ids' => $ids,
                        'filtros' => $filtros,
                        'titulo' => 'Cobertura de Manutenção Preventiva',
                        // Sem closures aqui -- Cache::put() serializa o
                        // payload (CACHE_STORE=database), e Closure não é
                        // serializável. O controller reconhece este report
                        // e monta as colunas (com o Status PMP calculado)
                        // do lado dele, não aqui.
                        'report' => 'cobertura_pmp',
                    ], now()->addMinutes(15));

                    return route('table-print.show', ['token' => $token]);
                })
                ->openUrlInNewTab(),
        ];
    }

    /**
     * sem_grupo: ativo sem grupo, ou grupo sem nenhum plano ativo aplicável.
     * vencido: algum plano aplicável com is_overdue=true.
     * vencendo: nenhum vencido, mas algum projectedDueDates($asset,0) não-vazio.
     * em_dia: nenhum vencido, nenhuma projeção pro mês atual.
     */
    public static function statusFor(Asset $asset): string
    {
        $plans = MaintenancePlan::applicableFor($asset)->where('is_active', true);

        if ($plans->isEmpty()) {
            return 'sem_grupo';
        }

        $temVencido = $plans->contains(fn (MaintenancePlan $plan) => $plan->dueStatusForAsset($asset)['is_overdue']);

        if ($temVencido) {
            return 'vencido';
        }

        $temVencendoEsteMes = $plans->contains(
            fn (MaintenancePlan $plan) => collect($plan->projectedDueDates($asset, 0))->isNotEmpty()
        );

        return $temVencendoEsteMes ? 'vencendo' : 'em_dia';
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'em_dia' => 'success',
            'vencendo' => 'warning',
            'vencido' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'em_dia' => 'Em Dia',
            'vencendo' => 'Vencendo',
            'vencido' => 'Vencido',
            default => 'Sem Grupo',
        };
    }

    public static function abrirOs(string $assetId): ?string
    {
        $asset = Asset::find($assetId);

        if (! $asset) {
            return null;
        }

        $order = MaintenanceOrder::create([
            'tenant_id' => $asset->tenant_id,
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => 'Planejada via Cobertura de PMP.',
        ]);

        return $order->id;
    }

    public function getTableQuery(): Builder
    {
        return Asset::query();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')->label('Ativo')->searchable(),
                TextColumn::make('patrimonio')->label('Patrimônio'),
                TextColumn::make('checklistGroup.name')->label('Grupo')->placeholder('Sem grupo'),
                TextColumn::make('pmp_status')
                    ->label('Status PMP')
                    ->badge()
                    ->state(fn (Asset $record) => static::statusLabel(static::statusFor($record)))
                    ->color(fn (Asset $record) => static::statusColor(static::statusFor($record))),
            ])
            ->filters([
                SelectFilter::make('pmp_status')
                    ->label('Status PMP')
                    ->options([
                        'sem_grupo' => 'Sem Grupo',
                        'em_dia' => 'Em Dia',
                        'vencendo' => 'Vencendo',
                        'vencido' => 'Vencido',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $ids = Asset::query()->get()->filter(
                            fn (Asset $asset) => static::statusFor($asset) === $data['value']
                        )->pluck('id');

                        return $query->whereIn('id', $ids);
                    }),
                SelectFilter::make('checklist_group_id')
                    ->label('Grupo de Ativo')
                    ->relationship('checklistGroup', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('asset_category_id')
                    ->label('Categoria de Ativo')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('internal_unit_id')
                    ->label('Unidade Interna')
                    ->relationship('internalUnit', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions([
                Action::make('abrir_os')
                    ->label('Abrir OS')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (Asset $record) => in_array(static::statusFor($record), ['vencendo', 'vencido'], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Asset $record) => 'Cria uma OS preventiva para "'.$record->name.'" cobrindo todos os planos vencidos/vencendo, e já entra na Fila de Alocação de Técnicos.')
                    ->action(function (Asset $record) {
                        $orderId = static::abrirOs($record->id);

                        Notification::make()->title('OS criada')->success()->send();

                        return redirect(MaintenanceOrderResource::getUrl('edit', ['record' => $orderId]));
                    }),
            ]);
    }
}
