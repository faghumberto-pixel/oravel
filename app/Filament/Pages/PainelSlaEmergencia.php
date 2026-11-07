<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;
use App\Services\Sla\SlaKpis;
use App\Services\Sla\SlaService;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Painel de SLA (F2): todas as O.S. com cronômetro aberto (Resposta, Atendimento, Resolução), das mais críticas para as menos,
 * e (F4) os indicadores do período escolhido: % cumprido, tempo médio e vencidos agora por prioridade, criticidade, tipo, técnico e cliente,
 * com exportação em CSV. Mantém a classe/rota/chave de menu do antigo painel de Emergência.
 */
class PainelSlaEmergencia extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationParentItem = 'Painéis';

    protected static ?string $navigationLabel = 'Painel de SLA';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Painel de SLA';

    protected static string $view = 'filament.pages.painel-sla-emergencia';

    /** Período dos indicadores, em dias. */
    #[Url]
    public int $dias = 30;

    /** Relógio usado nas quebras por dimensão: response | service | resolution. */
    #[Url]
    public string $relogio = SlaTimer::RESOLUTION;

    public function mount(): void
    {
        $this->dias = in_array($this->dias, [7, 30, 90], true) ? $this->dias : 30;
        $this->relogio = array_key_exists($this->relogio, SlaTimer::CLOCKS) ? $this->relogio : SlaTimer::RESOLUTION;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')->label('Exportar CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => $this->exportar()),
        ];
    }

    public function exportar()
    {
        $linhas = SlaKpis::detalhe((string) Tenancy::current()?->id, $this->dias);
        $cabecalho = ['OS', 'Tipo', 'Prioridade', 'Técnico', 'Cliente', 'Equipamento', 'Relógio', 'Meta (min)', 'Início', 'Fim', 'Tempo gasto (min)', 'Situação'];

        return response()->streamDownload(function () use ($linhas, $cabecalho) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos certos
            fputcsv($f, $cabecalho, ';');
            foreach ($linhas as $l) {
                fputcsv($f, [$l['os'], $l['tipo'], $l['prioridade'], $l['tecnico'], $l['cliente'], $l['ativo'], $l['relogio'], $l['meta_min'], $l['inicio'], $l['fim'], $l['gasto_min'], $l['situacao']], ';');
            }
            fclose($f);
        }, 'indicadores-sla-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{geral: array<string, mixed>, dimensoes: array<string, array<int, array<string, mixed>>>} */
    public function getIndicadoresProperty(): array
    {
        $tenantId = (string) Tenancy::current()?->id;

        return [
            'geral' => SlaKpis::geral($tenantId, $this->dias),
            'dimensoes' => collect(['prioridade', 'criticidade', 'tipo', 'tecnico', 'cliente'])
                ->mapWithKeys(fn ($d) => [$d => SlaKpis::porDimensao($tenantId, $d, $this->dias, $this->relogio)])->all(),
        ];
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getChamadosProperty(): Collection
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return collect();
        }

        $avisarEm = SlaService::settings((string) $tenant->id)->warn_pct;
        $ordem = ['vencido' => 0, 'em_risco' => 1, 'no_prazo' => 2, 'pausado' => 3];

        return MaintenanceOrder::where('tenant_id', $tenant->id)
            ->whereHas('slaTimers', fn ($q) => $q->whereNull('stopped_at'))
            ->with(['asset', 'client', 'technician', 'slaTimers'])
            ->get()
            ->map(function (MaintenanceOrder $order) use ($avisarEm, $ordem) {
                $relogios = $order->slaTimers->filter(fn (SlaTimer $t) => $t->aberto())->map(function (SlaTimer $t) use ($avisarEm) {
                    $estado = $t->estado($avisarEm);

                    return ['label' => $t->label(), 'estado' => $estado, 'rotulo' => SlaTimer::rotuloDoEstado($estado), 'cor' => SlaTimer::corDoEstado($estado),
                        'restante' => match ($estado) {
                            'pausado' => null,
                            'vencido' => 'há '.SlaService::formatarMinutos(abs($t->minutosRestantes())),
                            default => SlaService::formatarMinutos($t->minutosRestantes()),
                        }, 'meta' => SlaService::formatarMinutos($t->target_minutes)];
                })->values();

                $pior = $relogios->sortBy(fn ($r) => $ordem[$r['estado']] ?? 9)->first();

                return ['order' => $order, 'relogios' => $relogios, 'pior' => $pior, 'rank' => $ordem[$pior['estado']] ?? 9];
            })
            ->sortBy('rank')
            ->values();
    }

    public function getResumoProperty(): array
    {
        $tenant = Tenancy::current();

        return $tenant ? SlaService::resumo((string) $tenant->id, $this->dias) : ['relogios' => [], 'abertos' => []];
    }
}
