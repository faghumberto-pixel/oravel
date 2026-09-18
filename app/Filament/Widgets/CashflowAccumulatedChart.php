<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Charts\LineChartWithMarkers;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

class CashflowAccumulatedChart extends LineChartWithMarkers
{
    #[Reactive]
    public ?string $dateStart = null;

    #[Reactive]
    public ?string $dateEnd = null;

    #[Reactive]
    public ?string $status = null;

    #[Reactive]
    public ?string $type = null;

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

        $dateStart = $this->dateStart ? Carbon::parse($this->dateStart) : Carbon::today();
        $dateEnd = $this->dateEnd ? Carbon::parse($this->dateEnd) : now()->addDays(90);

        // Filter by status if specified
        $statusFilter = [];
        if ($this->status === 'pendente') {
            $statusFilter = ['pendente'];
        } elseif ($this->status === 'atrasado') {
            $statusFilter = ['atrasado'];
        } elseif ($this->status === 'pago') {
            $statusFilter = ['pago'];
        } else {
            $statusFilter = ['pendente', 'atrasado', 'pago'];
        }

        // Entradas (AccountReceivable) por due_date
        $entradas = AccountReceivable::where('tenant_id', $tenant->id)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when($this->status, fn ($q) => $q->whereIn('status', $statusFilter))
            ->when($this->type && $this->type !== 'AR', fn ($q) => $q->where(false))
            ->selectRaw("due_date, sum(case when status in ('pendente', 'atrasado') then amount else 0 end) as projetado, sum(case when status = 'pago' then amount else 0 end) as realizado")
            ->groupBy('due_date')
            ->orderBy('due_date')
            ->get()
            ->keyBy('due_date');

        // Saídas (AccountPayable) por due_date
        $saidas = AccountPayable::where('tenant_id', $tenant->id)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when($this->status, fn ($q) => $q->whereIn('status', $statusFilter))
            ->when($this->type && $this->type !== 'AP', fn ($q) => $q->where(false))
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
            chartTitle: 'Saldo Acumulado',
        );
    }
}
