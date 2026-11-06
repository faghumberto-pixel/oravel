<?php

namespace App\Filament\Pages;

use App\Dashboards\Payload;
use App\Models\StockPlacement;
use App\Services\Estoque\RastreioDoItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/** Rastreio de um item: tudo que aconteceu com ele (entradas, saídas, ajustes, uso em OS e na frota, contagens), em ordem. */
class RastreioDoItemPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?string $navigationLabel = 'Rastreio do Item';

    protected static ?string $title = 'Rastreio do Item';

    protected static ?string $slug = 'rastreio-do-item';

    protected static ?int $navigationSort = 14;

    protected static string $view = 'filament.pages.rastreio-do-item';

    #[Url(as: 'item')]
    public ?string $item = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', StockPlacement::class);
    }

    public function mount(): void
    {
        $this->form->fill(['item' => $this->item]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('item')->label('Qual item você quer rastrear?')->placeholder('Digite o nome, código ou SKU...')->searchable()->live()
                ->getSearchResultsUsing(fn (string $busca) => app(RastreioDoItem::class)->buscar($busca))
                ->options(fn () => app(RastreioDoItem::class)->buscar(''))
                ->getOptionLabelUsing(fn ($value) => app(RastreioDoItem::class)->buscar('')[$value] ?? $this->cabecalhoDe($value)['nome'] ?? $value)
                ->afterStateUpdated(fn ($state) => $this->item = $state),
        ])->statePath('data');
    }

    /** @return array{0: string, 1: string}|null */
    private function partes(): ?array
    {
        if (! $this->item || ! str_contains($this->item, '|')) {
            return null;
        }

        return explode('|', $this->item, 2);
    }

    private function cabecalhoDe(?string $valor): ?array
    {
        if (! $valor || ! str_contains($valor, '|')) {
            return null;
        }
        [$tipo, $id] = explode('|', $valor, 2);

        return app(RastreioDoItem::class)->cabecalho($tipo, $id);
    }

    protected function getViewData(): array
    {
        $partes = $this->partes();
        if (! $partes) {
            return ['cabecalho' => null, 'totais' => null, 'timeline' => null, 'eventos' => collect()];
        }

        $servico = app(RastreioDoItem::class);
        $cabecalho = $servico->cabecalho($partes[0], $partes[1]);
        $eventos = $cabecalho ? $servico->eventos($partes[0], $partes[1]) : collect();
        $rotulos = ['entrada' => 'entrada', 'saida' => 'saída', 'ajuste' => 'ajuste', 'uso' => 'uso', 'contagem' => 'contagem'];
        $fmt = fn (?float $v) => $v === null ? null : rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');

        $timeline = Payload::timeline($eventos->take(60)->map(fn ($e) => [
            $e['quando']->format('d/m/Y H:i'), $e['titulo'],
            trim($e['detalhe'].($e['quantidade'] !== null ? ' · '.($e['quantidade'] > 0 ? '+' : '').$fmt($e['quantidade']) : '').($e['saldo'] !== null ? ' · saldo '.$fmt($e['saldo']) : '').($e['quem'] ? ' · por '.$e['quem'] : ''), ' ·'),
            $e['tom'], $rotulos[$e['tipo']] ?? $e['tipo'],
        ])->all(), 'Nenhum registro para este item ainda.');

        return ['cabecalho' => $cabecalho, 'totais' => $servico->totais($eventos), 'timeline' => $timeline, 'eventos' => $eventos];
    }
}
