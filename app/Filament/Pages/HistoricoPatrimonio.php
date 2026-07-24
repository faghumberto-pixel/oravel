<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderPendencia;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Histórico do Patrimônio: pedido explícito do usuário a partir do Painel
 * de Criticidade -- "tudo que for apontado em todas as tabelas" sobre um
 * Ativo, num painel com métricas/gráficos igual ao Dashboard PMP, filtrável
 * por Patrimônio, intervalo de data e tipo de evento.
 *
 * Cruza 4 fontes que HOJE não tem nenhuma tabela de histórico em comum
 * (mesma limitação já documentada na Linha do Tempo da Locação):
 * - EquipmentDamage (severidade grave -> "criticidade", demais -> "problemas
 *   reportados") -- não existe histórico de MUDANÇA de nível ABC (AbcMatrix
 *   é um snapshot único por Ativo), então "criticidade" aqui é o subconjunto
 *   de avarias graves, não uma trilha de nível.
 * - MaintenanceOrder (tag "ordens_de_servico" sempre + preventivas/
 *   corretivas/trocas conforme maintenance_type)
 * - MaintenanceOrderPendencia (via maintenanceOrder.asset_id, sem FK direta)
 * - EquipmentReplacement (Ativo como original OU substituto)
 */
class HistoricoPatrimonio extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Histórico do Patrimônio';

    protected static ?string $title = 'Histórico do Patrimônio';

    protected static ?string $slug = 'patrimonio/historico/{assetId?}';

    protected static string $view = 'filament.pages.historico-patrimonio';

    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    // Nome diferente do parametro de mount() de proposito -- mesmo motivo
    // documentado em AssetDossier::$asset.
    public ?Asset $asset = null;

    public string $query = '';

    /** @var array<int, array{id: string, name: string, patrimonio: ?string, tag: ?string}> */
    public array $searchResults = [];

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $tipo = 'todos';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Asset::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(?string $assetId = null): void
    {
        if ($assetId) {
            $this->asset = Asset::find($assetId);
        }

        $this->dateFrom = now()->subMonths(5)->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    /**
     * Mesma busca flexível de AssetDossier::search() -- reaproveitada de
     * propósito, não reinventada.
     */
    public function search(): void
    {
        $this->validate(['query' => 'required|string']);
        $this->searchResults = [];

        $found = Asset::search($this->query);

        if ($found->isEmpty()) {
            Notification::make()
                ->title('Nenhum ativo encontrado.')
                ->body('Tente parte do patrimônio, nome, tag ou número de série.')
                ->danger()
                ->send();

            return;
        }

        if ($found->count() === 1) {
            $this->redirect(static::getUrl(['assetId' => $found->first()->id]));

            return;
        }

        $this->searchResults = $found->map(fn (Asset $asset) => [
            'id' => $asset->id,
            'name' => $asset->name,
            'patrimonio' => $asset->patrimonio,
            'tag' => $asset->tag,
        ])->all();
    }

    public function selectResult(string $assetId): void
    {
        $this->redirect(static::getUrl(['assetId' => $assetId]));
    }

    public function clear(): void
    {
        $this->redirect(static::getUrl());
    }

    public function tipoOptions(): array
    {
        return [
            'todos' => 'Todos',
            'criticidade' => 'Criticidade',
            'pendencias' => 'Pendências',
            'ordens_de_servico' => 'Ordens de Serviço',
            'preventivas' => 'Preventivas',
            'corretivas' => 'Corretivas',
            'trocas' => 'Trocas',
            'problemas_reportados' => 'Problemas Reportados',
        ];
    }

    /**
     * Todos os eventos do Ativo, sem filtro de data/tipo ainda -- cada
     * evento carrega um array 'tipos' (pode pertencer a mais de uma
     * categoria, ex: uma OS Preventiva é "ordens_de_servico" E
     * "preventivas" ao mesmo tempo).
     *
     * @return Collection<int, array{at: Carbon, tipos: array<string>, title: string, body: ?string}>
     */
    public function getAllEvents(): Collection
    {
        if (! $this->asset) {
            return collect();
        }

        $events = collect();

        foreach ($this->asset->damages as $damage) {
            $critico = $damage->severity === EquipmentDamage::SEVERITY_GRAVE;
            $tipoLabel = EquipmentDamage::damageTypeLabels()[$damage->damage_type] ?? $damage->damage_type;

            $events->push([
                'at' => $damage->created_at,
                'tipos' => $critico ? ['criticidade'] : ['problemas_reportados'],
                'title' => ($critico ? 'Avaria grave registrada' : 'Problema reportado').' — '.$tipoLabel,
                'body' => $damage->description ? Str::limit($damage->description, 90) : null,
            ]);
        }

        foreach ($this->asset->maintenanceOrders as $order) {
            $tipos = ['ordens_de_servico'];
            $tipos[] = match ($order->maintenance_type) {
                MaintenanceOrder::TYPE_PREVENTIVE => 'preventivas',
                MaintenanceOrder::TYPE_CORRECTIVE => 'corretivas',
                MaintenanceOrder::TYPE_TROCA => 'trocas',
                default => null,
            };
            $tipos = array_values(array_filter($tipos));

            $osLabel = 'OS #'.($order->os_number ?? Str::substr($order->id, 0, 8));
            $body = $osLabel;
            if ($order->finished_at) {
                $body .= ' — concluída em '.$order->finished_at->format('d/m/Y');
            }

            $events->push([
                'at' => $order->created_at,
                'tipos' => $tipos,
                'title' => 'Ordem de Serviço aberta ('.$order->maintenance_type.')',
                'body' => $body,
            ]);
        }

        $pendencias = MaintenanceOrderPendencia::whereHas(
            'maintenanceOrder',
            fn ($q) => $q->where('asset_id', $this->asset->id)
        )->get();

        foreach ($pendencias as $pendencia) {
            $body = $pendencia->description ? Str::limit($pendencia->description, 90) : null;
            $body = ($body ?? '').($pendencia->status === MaintenanceOrderPendencia::STATUS_RESOLVIDA
                ? ' — resolvida em '.$pendencia->resolved_at?->format('d/m/Y')
                : ' — em aberto');

            $events->push([
                'at' => $pendencia->created_at,
                'tipos' => ['pendencias'],
                'title' => 'Pendência registrada',
                'body' => trim($body, ' —') === '' ? null : $body,
            ]);
        }

        $trocas = EquipmentReplacement::where('original_asset_id', $this->asset->id)
            ->orWhere('replacement_asset_id', $this->asset->id)
            ->get();

        foreach ($trocas as $troca) {
            $papel = $troca->original_asset_id === $this->asset->id ? 'ativo original' : 'ativo substituto';

            $events->push([
                'at' => $troca->created_at,
                'tipos' => ['trocas'],
                'title' => 'Solicitação de troca ('.$papel.')',
                'body' => $troca->reason ? Str::limit($troca->reason, 90) : null,
            ]);
        }

        return $events->filter(fn ($e) => $e['at'] !== null)->sortByDesc('at')->values();
    }

    public function getFilteredEvents(): Collection
    {
        $events = $this->getAllEvents();

        if ($this->dateFrom) {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $events = $events->filter(fn ($e) => $e['at']->gte($from));
        }

        if ($this->dateTo) {
            $to = Carbon::parse($this->dateTo)->endOfDay();
            $events = $events->filter(fn ($e) => $e['at']->lte($to));
        }

        if ($this->tipo !== 'todos') {
            $events = $events->filter(fn ($e) => in_array($this->tipo, $e['tipos'], true));
        }

        return $events->values();
    }

    public function getKpis(): array
    {
        $events = $this->getFilteredEvents();

        return [
            'total' => $events->count(),
            'criticidade' => $events->filter(fn ($e) => in_array('criticidade', $e['tipos'], true))->count(),
            'pendencias' => $events->filter(fn ($e) => in_array('pendencias', $e['tipos'], true))->count(),
            'osTrocas' => $events->filter(fn ($e) => array_intersect(['ordens_de_servico', 'trocas'], $e['tipos']))->count(),
            'problemas' => $events->filter(fn ($e) => in_array('problemas_reportados', $e['tipos'], true))->count(),
        ];
    }

    /**
     * Série mensal (barras) dos eventos filtrados, dentro do intervalo de
     * data selecionado -- limitada a 24 meses pra não explodir num range
     * absurdamente largo.
     */
    public function getEvolutionSeries(): array
    {
        $events = $this->getFilteredEvents();

        $start = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfMonth() : now()->subMonths(5)->startOfMonth();
        $end = $this->dateTo ? Carbon::parse($this->dateTo)->startOfMonth() : now()->startOfMonth();

        $months = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end) && $months->count() < 24) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        return $months->map(fn (Carbon $m) => [
            'label' => self::MESES_ABREV[$m->month].'/'.$m->format('y'),
            'total' => $events->filter(fn ($e) => $e['at']->isSameMonth($m))->count(),
        ])->values()->all();
    }

    /**
     * Proporção por tipo (1 categoria "principal" por evento, ordem de
     * prioridade abaixo) -- pra não somar o mesmo evento 2x num gráfico de
     * proporção quando ele pertence a 2 tipos ao mesmo tempo.
     */
    public function getTypeBreakdown(): array
    {
        $events = $this->getFilteredEvents();
        $priority = ['criticidade', 'problemas_reportados', 'pendencias', 'trocas', 'preventivas', 'corretivas', 'ordens_de_servico'];
        $labels = $this->tipoOptions();

        $counts = array_fill_keys($priority, 0);
        foreach ($events as $event) {
            foreach ($priority as $tipo) {
                if (in_array($tipo, $event['tipos'], true)) {
                    $counts[$tipo]++;

                    break;
                }
            }
        }

        $counts = array_filter($counts);
        $total = max(array_sum($counts), 1);

        return collect($counts)->map(fn ($count, $tipo) => [
            'tipo' => $tipo,
            'label' => $labels[$tipo] ?? $tipo,
            'count' => $count,
            'pct' => (int) round($count / $total * 100),
        ])->sortByDesc('count')->values()->all();
    }
}
