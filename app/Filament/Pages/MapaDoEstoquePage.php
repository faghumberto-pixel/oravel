<?php

namespace App\Filament\Pages;

use App\Models\InternalUnit;
use App\Models\StockGroup;
use App\Models\StockLocation;
use App\Models\StockPlacement;
use App\Services\Estoque\MapaDoEstoque;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/** Mapa visual do almoxarifado: cada corredor e' uma coluna, com prateleiras e niveis coloridos pela situacao do estoque, pelo grupo ou pela contagem. */
class MapaDoEstoquePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?string $navigationLabel = 'Mapa do Estoque';

    protected static ?string $title = 'Mapa do Estoque';

    protected static ?string $slug = 'mapa-do-estoque';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.mapa-do-estoque';

    #[Url]
    public ?string $unidade = null;

    #[Url]
    public string $modo = MapaDoEstoque::MODO_SITUACAO;

    #[Url]
    public ?string $grupo = null;

    #[Url]
    public ?string $busca = null;

    public ?string $selecionado = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', StockPlacement::class);
    }

    public function mount(): void
    {
        $this->unidade ??= (string) (StockLocation::query()->orderBy('code')->value('internal_unit_id') ?? InternalUnit::query()->orderBy('name')->value('id'));
    }

    public function selecionar(?string $id): void
    {
        $this->selecionado = $this->selecionado === $id ? null : $id;
    }

    public function escolherGrupo(?string $id): void
    {
        $this->grupo = $this->grupo === $id ? null : $id;
    }

    public function limpar(): void
    {
        $this->reset(['grupo', 'busca', 'selecionado']);
    }

    protected function getViewData(): array
    {
        $servico = app(MapaDoEstoque::class);

        return [
            'mapa' => $this->unidade ? $servico->montar($this->unidade, ['modo' => $this->modo, 'grupo' => $this->grupo, 'busca' => $this->busca]) : ['colunas' => [], 'legenda' => [], 'resumo' => ['enderecos' => 0, 'ocupados' => 0, 'vazios' => 0, 'com_problema' => 0, 'destacados' => 0], 'contagem' => null],
            'detalhe' => $this->selecionado ? $servico->detalhe($this->selecionado) : null,
            'unidades' => InternalUnit::query()->whereIn('id', StockLocation::query()->select('internal_unit_id'))->orderBy('name')->pluck('name', 'id'),
            'grupos' => StockGroup::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']),
        ];
    }
}
