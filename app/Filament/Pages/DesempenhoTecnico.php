<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Desempenho: agregado por tecnico, com link "abrir" pra lista real de OS
 * por tras de cada numero (nao so o total).
 *
 * Retrabalho: lista linha a linha (nao agregado) -- data, tecnico, cliente,
 * local, dano, tipo. Definicao usada (a mais honesta dado o que existe no
 * schema hoje): mesmo ativo recebeu uma OS nova dentro da janela escolhida
 * depois de uma OS anterior ter sido concluida. Nao exigimos que seja o
 * mesmo tecnico nem o mesmo "tipo de problema" (reported_problem e texto
 * livre, nao da pra comparar com confianca) -- mostramos os dois lados
 * (tecnico da OS antiga e da nova) e deixamos o gestor julgar.
 */
class DesempenhoTecnico extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Desempenho & Retrabalho';

    protected static ?string $title = 'Desempenho e Retrabalho de Técnicos';

    protected static string $view = 'filament.pages.desempenho-tecnico';

    public int $janelaRetrabalho = 30;

    public function getJanelaOptions(): array
    {
        return [15 => '15 dias', 30 => '30 dias', 60 => '60 dias', 90 => '90 dias'];
    }

    public function getDesempenhoProperty(): Collection
    {
        $tenantId = Tenancy::current()?->id;

        if (! $tenantId) {
            return collect();
        }

        return MaintenanceOrder::query()
            ->whereIn('status', ['Concluída'])
            ->whereNotNull('technician_id')
            ->select('technician_id')
            ->selectRaw("count(*) filter (where maintenance_type = 'Corretiva') as corretivas")
            ->selectRaw("count(*) filter (where maintenance_type = 'Preventiva') as preventivas")
            // total_time_seconds negativo/zero e ruido historico (bug do Carbon 3,
            // corrigido em cede603) -- nao entra na media pra nao distorcer.
            ->selectRaw("avg(total_time_seconds) filter (where maintenance_type = 'Corretiva' and total_time_seconds > 0) as media_corretiva")
            ->selectRaw("avg(total_time_seconds) filter (where maintenance_type = 'Preventiva' and total_time_seconds > 0) as media_preventiva")
            ->groupBy('technician_id')
            ->with('technician')
            ->get()
            ->filter(fn ($linha) => $linha->technician)
            ->map(fn ($linha) => [
                'technician' => $linha->technician,
                'corretivas' => (int) $linha->corretivas,
                'preventivas' => (int) $linha->preventivas,
                'media_corretiva' => $linha->media_corretiva ? $this->formatSeconds((float) $linha->media_corretiva) : '—',
                'media_preventiva' => $linha->media_preventiva ? $this->formatSeconds((float) $linha->media_preventiva) : '—',
                'url_corretivas' => $this->technicianOrdersUrl($linha->technician_id, 'Corretiva'),
                'url_preventivas' => $this->technicianOrdersUrl($linha->technician_id, 'Preventiva'),
            ])
            ->sortByDesc(fn ($linha) => $linha['corretivas'] + $linha['preventivas'])
            ->values();
    }

    public function getRetrabalhosProperty(): Collection
    {
        $anteriores = MaintenanceOrder::query()
            ->whereIn('status', ['Concluída'])
            ->whereNotNull('finished_at')
            ->whereNotNull('asset_id')
            ->orderBy('finished_at')
            ->get(['id', 'asset_id', 'finished_at']);

        if ($anteriores->isEmpty()) {
            return collect();
        }

        $porAsset = $anteriores->groupBy('asset_id');
        $assetIds = $porAsset->keys();

        // Todas as OS desses ativos (pra achar "a proxima depois da conclusao"),
        // ja com o que precisamos pra exibir a linha sem N+1 por registro.
        $todasPorAsset = MaintenanceOrder::query()
            ->whereIn('asset_id', $assetIds)
            ->with(['technician', 'client', 'asset.client', 'reportedProblem'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('asset_id');

        $danosPorOs = EquipmentDamage::query()
            ->whereIn('maintenance_order_id', MaintenanceOrder::whereIn('asset_id', $assetIds)->pluck('id'))
            ->get()
            ->keyBy('maintenance_order_id');

        $janela = $this->janelaRetrabalho;
        $linhas = collect();

        foreach ($porAsset as $assetId => $concluidasDoAtivo) {
            $todasDoAtivo = $todasPorAsset->get($assetId, collect());

            foreach ($concluidasDoAtivo as $osAntiga) {
                $limite = $osAntiga->finished_at->copy()->addDays($janela);

                $proxima = $todasDoAtivo->first(fn (MaintenanceOrder $candidata) => $candidata->id !== $osAntiga->id
                    && $candidata->created_at
                    && $candidata->created_at->greaterThan($osAntiga->finished_at)
                    && $candidata->created_at->lessThanOrEqualTo($limite)
                );

                if (! $proxima) {
                    continue;
                }

                $osAntigaCompleta = $todasDoAtivo->firstWhere('id', $osAntiga->id);
                $dano = $danosPorOs->get($proxima->id);
                $cliente = $proxima->client ?? $proxima->asset?->client;

                $linhas->push([
                    'data' => $proxima->created_at,
                    'os' => $proxima,
                    'os_anterior' => $osAntigaCompleta,
                    'tecnico' => $proxima->technician?->name ?? '—',
                    'tecnico_anterior' => $osAntigaCompleta?->technician?->name ?? '—',
                    'cliente' => $cliente?->name ?? '—',
                    'local' => $cliente?->city ? "{$cliente->city}/{$cliente->uf}" : '—',
                    'dano' => $dano
                        ? (EquipmentDamage::damageTypeLabels()[$dano->damage_type] ?? 'Não classificado')
                        : ($proxima->reportedProblem?->description ?? $proxima->description ?? '—'),
                    'tipo' => $proxima->maintenance_type ?? '—',
                    'dias_depois' => (int) $osAntiga->finished_at->diffInDays($proxima->created_at, true),
                ]);
            }
        }

        return $linhas->sortByDesc('data')->values();
    }

    private function technicianOrdersUrl(string $technicianId, string $maintenanceType): string
    {
        return MaintenanceOrderResource::getUrl('index', [
            'tableFilters' => [
                'technician_id' => ['value' => $technicianId],
                'status' => ['values' => ['Concluída']],
                'maintenance_type' => ['value' => $maintenanceType],
            ],
        ]);
    }

    private function formatSeconds(float $seconds): string
    {
        $seconds = (int) round($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
    }
}
