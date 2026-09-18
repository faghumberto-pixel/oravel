<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Charts\LineChartWithMarkers;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Gráfico de saldo acumulado ao longo do período projetado (próximos 90 dias).
 * Mostra duas linhas:
 * - "Projetado": entradas (pendente+atrasado) - saídas (pendente+atrasado)
 * - "Realizado": entradas (pago) - saídas (pago)
 *
 * Agrupa por due_date e acumula o saldo dia a dia.
 */
class CashflowAccumulatedChart extends LineChartWithMarkers
{
    public function mount(
        array $labels = [],
        array $series = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        string $markerStyle = 'circle',
    ): void {
        $tenant = Tenancy::current();
        if (!$tenant) {
            parent::mount(labels: [], series: [], chartTitle: 'Fluxo de Caixa');
            return;
        }

        $hoje = Carbon::today();
        $limite = now()->addDays(90);

        // Entradas (AccountReceivable) por due_date
        $entradas = AccountReceivable::where('tenant_id', $tenant->id)
            ->whereBetween('due_date', [$hoje, $limite])
            ->selectRaw("due_date, sum(case when status in ('pendente', 'atrasado') then amount else 0 end) as projetado, sum(case when status = 'pago' then amount else 0 end) as realizado")
            ->groupBy('due_date')
            ->orderBy('due_date')
            ->get()
            ->keyBy('due_date');

        // Saídas (AccountPayable) por due_date
        $saidas = AccountPayable::where('tenant_id', $tenant->id)
            ->whereBetween('due_date', [$hoje, $limite])
            ->selectRaw("due_date, sum(case when status in ('pendente', 'atrasado') then amount else 0 end) as projetado, sum(case when status = 'pago' then amount else 0 end) as realizado")
            ->groupBy('due_date')
            ->orderBy('due_date')
            ->get()
            ->keyBy('due_date');

        // Merge de todas as datas únicas
        $todasDatas = array_unique(array_merge(
            $entradas->keys()->map(fn ($d) => (string) $d)->all(),
            $saidas->keys()->map(fn ($d) => (string) $d)->all(),
        ));
        sort($todasDatas);

        $labels = [];
        $saldosProjetados = [];
        $saldosRealizados = [];
        $acumuladoProjetado = 0;
        $acumuladoRealizado = 0;

        foreach ($todasDatas as $dataStr) {
            $data = Carbon::parse($dataStr);
            $labels[] = $data->format('d/m');

            $entrada = $entradas->get($dataStr);
            $saida = $saidas->get($dataStr);

            $movimentoProjetado = ($entrada?->projetado ?? 0) - ($saida?->projetado ?? 0);
            $movimentoRealizado = ($entrada?->realizado ?? 0) - ($saida?->realizado ?? 0);

            $acumuladoProjetado += $movimentoProjetado;
            $acumuladoRealizado += $movimentoRealizado;

            $saldosProjetados[] = round($acumuladoProjetado, 2);
            $saldosRealizados[] = round($acumuladoRealizado, 2);
        }

        if (empty($labels)) {
            $labels = [now()->format('d/m')];
            $saldosProjetados = [0];
            $saldosRealizados = [0];
        }

        parent::mount(
            labels: $labels,
            series: [
                ['name' => 'Projetado', 'color' => '#f59e0b', 'data' => $saldosProjetados],
                ['name' => 'Realizado', 'color' => '#10b981', 'data' => $saldosRealizados],
            ],
            chartTitle: 'Saldo Acumulado - Próximos 90 Dias',
        );
    }
}
