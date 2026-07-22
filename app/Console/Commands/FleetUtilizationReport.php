<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\HorimeterReading;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Taxa de utilização = horas trabalhadas / (horas trabalhadas + horas
 * paradas) * 100 -- fração do tempo com visibilidade (horímetro rodando
 * OU parada registrada) que foi produtiva. Ativo sem nenhum apontamento
 * nem parada no período fica de fora do relatório (sem dado, não 0%).
 */
class FleetUtilizationReport extends Command
{
    protected $signature = 'fleet:utilization-report {--month=} {--tenant= : Slug do tenant (default: todos)}';

    protected $description = 'Horas trabalhadas, horas paradas e taxa de utilização por ativo, num mês';

    public function handle(): int
    {
        $month = $this->option('month') ? Carbon::parse($this->option('month').'-01') : now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $this->info('Período: '.$start->format('d/m/Y').' a '.$end->format('d/m/Y'));

        $tenants = $this->option('tenant')
            ? Tenant::where('slug', $this->option('tenant'))->get()
            : Tenant::orderBy('name')->get();

        foreach ($tenants as $tenant) {
            $rows = $this->buildRowsForTenant($tenant->id, $start, $end);

            if ($rows->isEmpty()) {
                continue;
            }

            $this->newLine();
            $this->line("<fg=yellow;options=bold>{$tenant->name}</>");

            $this->table(
                ['Ativo', 'Patrimônio', 'Horas Trabalhadas', 'Horas Paradas', 'Utilização', 'Maior Causa de Parada'],
                $rows->toArray()
            );
        }

        return self::SUCCESS;
    }

    private function buildRowsForTenant(string $tenantId, Carbon $start, Carbon $end): Collection
    {
        $assets = Asset::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('name')->get();

        return $assets->map(function (Asset $asset) use ($start, $end) {
            $horasTrabalhadas = $this->horasTrabalhadas($asset->id, $start, $end);
            [$horasParadas, $maiorCausa] = $this->horasParadas($asset->id, $start, $end);

            if ($horasTrabalhadas === null && $horasParadas === 0.0) {
                return null;
            }

            $trabalhadas = $horasTrabalhadas ?? 0.0;
            $totalVisivel = $trabalhadas + $horasParadas;
            $taxa = $totalVisivel > 0 ? ($trabalhadas / $totalVisivel) * 100 : 0.0;

            return [
                $asset->name,
                $asset->patrimonio ?? '—',
                number_format($trabalhadas, 1, ',', '.').'h',
                number_format($horasParadas, 1, ',', '.').'h',
                number_format($taxa, 1, ',', '.').'%',
                $maiorCausa,
            ];
        })->filter()->values();
    }

    /**
     * Última leitura do período menos a primeira -- se só existe 1 leitura
     * (ou nenhuma), não dá pra saber quanto rodou, retorna null (sem dado,
     * diferente de "rodou 0h").
     */
    private function horasTrabalhadas(string $assetId, Carbon $start, Carbon $end): ?float
    {
        $leituras = HorimeterReading::query()
            ->where('asset_id', $assetId)
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->pluck('reading');

        if ($leituras->count() < 2) {
            return null;
        }

        return max(0.0, (float) $leituras->last() - (float) $leituras->first());
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function horasParadas(string $assetId, Carbon $start, Carbon $end): array
    {
        $eventos = AssetDowntimeEvent::query()
            ->where('asset_id', $assetId)
            ->where('started_at', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $start);
            })
            ->get();

        if ($eventos->isEmpty()) {
            return [0.0, '—'];
        }

        $totalMinutos = $eventos->sum(function (AssetDowntimeEvent $evento) use ($start, $end) {
            $inicio = $evento->started_at->max($start);
            $fim = ($evento->ended_at ?? now())->min($end);

            return max(0, $inicio->diffInMinutes($fim, true));
        });

        $maiorCausa = $eventos->groupBy('reason')
            ->sortByDesc(fn ($grupo) => $grupo->count())
            ->keys()
            ->first();

        return [
            round($totalMinutos / 60, 1),
            $maiorCausa ? (AssetDowntimeEvent::reasonLabels()[$maiorCausa] ?? $maiorCausa) : '—',
        ];
    }
}
