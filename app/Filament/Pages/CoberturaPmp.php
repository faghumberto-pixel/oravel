<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

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

    public function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query();
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->label('Ativo')->searchable(),
                \Filament\Tables\Columns\TextColumn::make('patrimonio')->label('Patrimônio'),
                \Filament\Tables\Columns\TextColumn::make('checklistGroup.name')->label('Grupo')->placeholder('Sem grupo'),
                \Filament\Tables\Columns\TextColumn::make('pmp_status')
                    ->label('Status PMP')
                    ->badge()
                    ->state(fn (Asset $record) => static::statusLabel(static::statusFor($record)))
                    ->color(fn (Asset $record) => static::statusColor(static::statusFor($record))),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('pmp_status')
                    ->label('Status PMP')
                    ->options([
                        'sem_grupo' => 'Sem Grupo',
                        'em_dia' => 'Em Dia',
                        'vencendo' => 'Vencendo',
                        'vencido' => 'Vencido',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $ids = Asset::query()->get()->filter(
                            fn (Asset $asset) => static::statusFor($asset) === $data['value']
                        )->pluck('id');

                        return $query->whereIn('id', $ids);
                    }),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('abrir_os')
                    ->label('Abrir OS')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (Asset $record) => in_array(static::statusFor($record), ['vencendo', 'vencido'], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Asset $record) => 'Cria uma OS preventiva para "'.$record->name.'" cobrindo todos os planos vencidos/vencendo, e já entra na Fila de Alocação de Técnicos.')
                    ->action(function (Asset $record) {
                        $orderId = static::abrirOs($record->id);

                        \Filament\Notifications\Notification::make()->title('OS criada')->success()->send();

                        return redirect(\App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $orderId]));
                    }),
            ]);
    }
}
