<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Services\CustoManutencaoService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Custo de manutencao por equipamento (Inteligencia de Ativos): ranking de quem mais gasta, ao longo do tempo,
 * com sinalizacao para revisar, trocar ou vender. Opcional por contrato (chave menu_custo_equipamentos).
 */
class CustoPorEquipamento extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Inteligência de Ativos';

    protected static ?string $navigationLabel = 'Custo de Manutenção';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Custo de manutenção por equipamento';

    protected static ?string $slug = 'custo-manutencao-equipamentos';

    protected static string $view = 'filament.pages.custo-por-equipamento';

    public const PERIODOS = [6 => 'Últimos 6 meses', 12 => 'Últimos 12 meses', 18 => 'Últimos 18 meses', 24 => 'Últimos 24 meses'];

    public const ORDENS = [
        'periodo' => 'Maior custo no período',
        'razao' => 'Maior % do valor do equipamento',
        'total' => 'Maior custo acumulado',
        'retrabalho' => 'Mais retrabalho',
        'nome' => 'Nome (A–Z)',
    ];

    #[Url]
    public int $meses = 12;

    #[Url]
    public string $categoria = '';

    #[Url]
    public string $situacao = '';

    #[Url]
    public string $busca = '';

    #[Url]
    public string $ordem = 'periodo';

    public static function canAccess(): bool
    {
        return AssetResource::canViewAny() && CustoManutencaoService::habilitado();
    }

    public function updatedMeses(): void
    {
        $this->meses = array_key_exists($this->meses, self::PERIODOS) ? $this->meses : 12;
    }

    public function filtrarCard(string $chave): void
    {
        $this->situacao = in_array($chave, ['trocar_vender', 'revisar', 'ok'], true) ? $chave : '';
    }

    /** @return array<int, string> */
    public function categorias(): array
    {
        return Asset::query()->whereNotNull('asset_category')->distinct()->orderBy('asset_category')->pluck('asset_category')->all();
    }

    /**
     * Linhas do ranking (ja filtradas e ordenadas) e os cards de totais (que respeitam busca e categoria, mas nao a
     * situacao escolhida, para cada card mostrar o proprio total).
     *
     * @return array{linhas: Collection, cards: array<int, array<string, mixed>>, periodo_total: float}
     */
    public function dados(): array
    {
        $todas = app(CustoManutencaoService::class)->ranking(
            array_key_exists($this->meses, self::PERIODOS) ? $this->meses : 12,
            $this->categoria !== '' ? $this->categoria : null,
            $this->busca !== '' ? $this->busca : null,
        );

        $cards = [];
        foreach ([
            ['chave' => 'total', 'rotulo' => 'Todos os equipamentos', 'cor' => 'gray', 'filtro' => fn () => true],
            ['chave' => 'trocar_vender', 'rotulo' => 'Considerar trocar ou vender', 'cor' => 'red', 'filtro' => fn ($l) => $l['situacao']['chave'] === 'trocar_vender'],
            ['chave' => 'revisar', 'rotulo' => 'Revisar', 'cor' => 'amber', 'filtro' => fn ($l) => $l['situacao']['chave'] === 'revisar'],
            ['chave' => 'ok', 'rotulo' => 'Custo sob controle', 'cor' => 'emerald', 'filtro' => fn ($l) => $l['situacao']['chave'] === 'ok'],
        ] as $c) {
            $grupo = $todas->filter($c['filtro']);
            $cards[] = [
                'chave' => $c['chave'], 'rotulo' => $c['rotulo'], 'cor' => $c['cor'],
                'quantidade' => $grupo->count(), 'custo' => (float) $grupo->sum('custo_periodo'),
                'ativo' => $this->situacao === ($c['chave'] === 'total' ? '' : $c['chave']),
            ];
        }

        $linhas = $this->situacao !== '' ? $todas->filter(fn ($l) => $l['situacao']['chave'] === $this->situacao) : $todas;

        $linhas = match ($this->ordem) {
            'razao' => $linhas->sortByDesc(fn ($l) => $l['razao'] ?? -1),
            'total' => $linhas->sortByDesc('custo_total'),
            'retrabalho' => $linhas->sortByDesc(fn ($l) => [$l['retrabalho'], $l['custo_periodo']]),
            'nome' => $linhas->sortBy(fn ($l) => mb_strtolower($l['nome'])),
            default => $linhas->sortByDesc('custo_periodo'),
        };

        return ['linhas' => $linhas->values(), 'cards' => $cards, 'periodo_total' => (float) $todas->sum('custo_periodo')];
    }
}
