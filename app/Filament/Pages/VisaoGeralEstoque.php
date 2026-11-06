<?php

namespace App\Filament\Pages;

use App\Dashboards\Payload;
use App\Models\InternalUnit;
use App\Models\StockGroup;
use App\Models\StockLocation;
use App\Models\StockPlacement;
use App\Services\Estoque\EstoqueGrupos;
use App\Services\Estoque\EstoqueUnificado;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Visao geral do estoque da empresa inteira: materiais, pecas/oleo e itens agregados juntos, com filtros por grupo (administracao,
 * equipamentos, veiculos, manutencao, logistica...), categoria, almoxarifado, corredor, situacao e endereco.
 */
class VisaoGeralEstoque extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?string $navigationLabel = 'Visão geral do Estoque';

    protected static ?string $title = 'Visão geral do Estoque';

    protected static ?string $slug = 'visao-geral-estoque';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.visao-geral-estoque';

    public const POR_PAGINA = 25;

    #[Url]
    public ?string $grupo = null;

    #[Url]
    public ?string $tipo = null;

    #[Url]
    public ?string $categoria = null;

    #[Url]
    public ?string $unidade = null;

    #[Url]
    public ?string $corredor = null;

    #[Url]
    public ?string $situacao = null;

    #[Url]
    public ?string $endereco = null;

    #[Url]
    public ?string $busca = null;

    public int $pagina = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', StockPlacement::class);
    }

    public function mount(): void
    {
        if ($tenantId = auth()->user()?->tenant_id) {
            app(EstoqueGrupos::class)->garantirPadrao((string) $tenantId);
        }
    }

    /** Qualquer mudanca de filtro volta para a primeira pagina. */
    public function updated(string $propriedade): void
    {
        if ($propriedade !== 'pagina') {
            $this->pagina = 1;
        }
    }

    public function escolherGrupo(?string $id): void
    {
        $this->grupo = $id;
        $this->pagina = 1;
    }

    public function limpar(): void
    {
        $this->reset(['grupo', 'tipo', 'categoria', 'unidade', 'corredor', 'situacao', 'endereco', 'busca']);
        $this->pagina = 1;
    }

    public function mudarPagina(int $delta): void
    {
        $this->pagina = max(1, $this->pagina + $delta);
    }

    /** @return array<string, ?string> */
    public function filtros(): array
    {
        return ['grupo' => $this->grupo, 'tipo' => $this->tipo, 'categoria' => $this->categoria, 'unidade' => $this->unidade, 'corredor' => $this->corredor, 'situacao' => $this->situacao, 'endereco' => $this->endereco, 'busca' => $this->busca];
    }

    protected function getViewData(): array
    {
        $servico = app(EstoqueUnificado::class);
        $todos = $servico->todos();
        $linhas = $servico->itens($this->filtros());
        $resumo = $servico->resumo($linhas);
        $total = $linhas->count();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $this->pagina = min($this->pagina, $paginas);

        $grupos = StockGroup::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']);
        $contagemPorGrupo = $todos->groupBy('grupo_id')->map->count();

        $donut = Payload::donut(
            $resumo['por_grupo']->map(fn ($g, $nome) => [$nome, round($g['valor']), $g['cor']])->filter(fn ($f) => $f[1] > 0)->values()->all(),
            'R$ '.number_format($resumo['valor'] / 1000, 1, ',', '.').' mil', 'em estoque',
        );
        $maxCat = max(1.0, (float) $resumo['por_categoria']->max('valor'));
        $ranking = Payload::ranking(
            $resumo['por_categoria']->take(8)->map(fn ($c, $nome) => [$nome, '', (int) round($c['valor'] / $maxCat * 100), 'R$ '.number_format($c['valor'], 0, ',', '.'), 'a'])->values()->all(),
            [], 'Valor em estoque por categoria; a barra compara com a maior.',
        );

        return [
            'resumo' => $resumo, 'donut' => $donut, 'ranking' => $ranking, 'total' => $total, 'paginas' => $paginas,
            'itens' => $linhas->forPage($this->pagina, self::POR_PAGINA)->values(),
            'grupos' => $grupos, 'contagemPorGrupo' => $contagemPorGrupo, 'semGrupo' => (int) ($contagemPorGrupo[''] ?? 0),
            'opcoes' => [
                'categorias' => $todos->pluck('categoria')->unique()->sort()->values(),
                'unidades' => InternalUnit::query()->whereIn('id', StockLocation::query()->select('internal_unit_id'))->orderBy('name')->pluck('name', 'id'),
                'corredores' => StockLocation::query()->distinct()->orderBy('aisle')->pluck('aisle'),
            ],
            'urlEtiquetas' => route('estoque.etiquetas', array_filter(['tipo' => 'itens', 'modelo' => 'media', 'grupo' => $this->grupo, 'tipo_item' => $this->tipo, 'categoria' => $this->categoria, 'unidade' => $this->unidade, 'corredor' => $this->corredor, 'situacao' => $this->situacao, 'endereco' => $this->endereco, 'busca' => $this->busca])),
        ];
    }
}
