<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Filament\Widgets\Charts\LineChartWithMarkers;
use App\Models\CrmLead;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Ponte entre o LineChartWithMarkers genérico (sem query interna, por
 * design) e o registro de widgets do cabeçalho da listagem
 * (ListCrmLeads::getHeaderWidgets(), que usa o grid real do Filament --
 * diferente do dashboard PainelGestao, aqui columnSpan funciona como
 * esperado).
 */
class CrmLeadsCreatedTrendWidget extends LineChartWithMarkers
{
    protected int|string|array $columnSpan = 'full';

    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy, $isLazy=true) -- getHeaderWidgets() carrega o
     * conteúdo real via uma requisição Livewire separada depois do
     * placeholder inicial. Desligado aqui: o gráfico é leve (1 query),
     * não precisa do adiamento, e lazy deixava impossível confirmar via
     * teste HTTP simples se o gráfico realmente aparece.
     */
    protected static bool $isLazy = false;

    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    /**
     * Assinatura repete a de LineChartWithMarkers::mount() de propósito
     * (parâmetros nunca usados) -- PHP não deixa subclasse declarar
     * mount() com assinatura mais restrita que a do pai.
     */
    public function mount(
        array $labels = [],
        array $series = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        string $markerStyle = 'circle',
    ): void {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $dados = $months->map(fn (Carbon $month) => CrmLead::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        parent::mount(
            labels: $labels,
            series: [
                ['name' => 'Leads', 'color' => '#3987e5', 'data' => $dados],
            ],
            chartTitle: 'Leads Criados por Mês',
        );
    }
}
