<?php

namespace App\Filament\Pages;

use App\Models\ActivityLogEntry;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrderPendencia;
use App\Models\MaintenancePlan;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Timeline unificada por Ativo -- junta 3 fontes que ja existiam soltas
 * em telas separadas (avaria, log de alteracao, preventiva vencida) sem
 * criar tabela nova, mesmo padrao de App\Filament\Pages\AvariasReincidencia
 * (Page com propriedades computadas + Collection, nao Resource/Table).
 *
 * "Preventiva vencida" nao entra na timeline de eventos datados (e' um
 * status vivo, nao um evento com data) -- fica numa secao propria,
 * reaproveitando a mesma logica de agrupamento por-Ativo/por-Grupo de
 * App\Filament\Pages\PainelCriticidade.
 */
class EventosEFalhas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Eventos e Falhas';

    protected static ?string $title = 'Eventos e Falhas';

    protected static string $view = 'filament.pages.eventos-e-falhas';

    protected static ?int $navigationSort = 23;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquipmentDamage::class)
            || (bool) auth()->user()?->can('viewAny', ActivityLogEntry::class);
    }

    public int $days = 90;

    public function getDaysOptions(): array
    {
        return [30 => '30 dias', 60 => '60 dias', 90 => '90 dias', 180 => '180 dias'];
    }

    public function getPendenciasAbertasProperty(): Collection
    {
        return MaintenanceOrderPendencia::query()
            ->where('status', MaintenanceOrderPendencia::STATUS_ABERTA)
            ->with(['maintenanceOrder.asset', 'createdBy'])
            ->oldest()
            ->get();
    }

    public function resolverPendencia(string $pendenciaId): void
    {
        $pendencia = MaintenanceOrderPendencia::query()->where('status', MaintenanceOrderPendencia::STATUS_ABERTA)->find($pendenciaId);

        if (! $pendencia) {
            return;
        }

        $pendencia->update([
            'status' => MaintenanceOrderPendencia::STATUS_RESOLVIDA,
            'resolved_at' => now(),
            'resolved_by_user_id' => auth()->id(),
        ]);

        Notification::make()->title('Pendência marcada como resolvida.')->success()->send();
    }

    public function getEventosProperty(): Collection
    {
        $desde = now()->subDays($this->days);

        $avarias = EquipmentDamage::query()
            ->with(['asset', 'reportedBy'])
            ->where('created_at', '>=', $desde)
            ->get()
            ->map(fn (EquipmentDamage $damage) => [
                'tipo' => 'avaria',
                'data' => $damage->created_at,
                'asset' => $damage->asset,
                'titulo' => 'Avaria: '.(EquipmentDamage::damageTypeLabels()[$damage->damage_type] ?? 'Não classificado'),
                'detalhe' => $damage->description,
                'autor' => $damage->reportedBy?->name,
            ]);

        // whereIn (string) em vez de whereHasMorph: Postgres nao faz cast
        // implicito varchar = uuid no join direto -- mesmo motivo documentado
        // em ActivityLogResource::getEloquentQuery().
        $assetsById = Asset::query()->get()->keyBy(fn (Asset $asset) => (string) $asset->id);

        $logs = ActivityLogEntry::query()
            ->with('causer')
            ->where('subject_type', Asset::class)
            ->where('created_at', '>=', $desde)
            ->whereIn('subject_id', $assetsById->keys())
            ->get()
            ->map(fn (ActivityLogEntry $entry) => [
                'tipo' => 'log',
                'data' => $entry->created_at,
                'asset' => $assetsById->get((string) $entry->subject_id),
                'titulo' => match ($entry->event) {
                    'created' => 'Ativo criado',
                    'updated' => 'Ativo editado',
                    'deleted' => 'Ativo excluído',
                    default => $entry->event,
                },
                'detalhe' => null,
                'autor' => $entry->causer?->name,
            ]);

        return $avarias->concat($logs)
            ->sortByDesc('data')
            ->values()
            ->take(200);
    }

    public function getPreventivasVencidasProperty(): Collection
    {
        $assets = Asset::query()->whereNotIn('status', ['aguardando_triagem'])->get();

        if ($assets->isEmpty()) {
            return collect();
        }

        $planos = MaintenancePlan::query()->get();

        return $assets->flatMap(function (Asset $asset) use ($planos) {
            $planosDoAtivo = MaintenancePlan::applicableFor($asset, $planos);

            return $planosDoAtivo
                ->map(fn (MaintenancePlan $plano) => ['asset' => $asset, 'plano' => $plano, 'status' => $plano->dueStatusForAsset($asset)])
                ->filter(fn (array $linha) => $linha['status']['is_overdue']);
        })
            ->sortByDesc(fn (array $linha) => $linha['status']['overdue_hours'])
            ->values();
    }
}
