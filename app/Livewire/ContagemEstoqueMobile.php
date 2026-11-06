<?php

namespace App\Livewire;

use App\Models\AggregateItem;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockPlacement;
use App\Services\Estoque\ContagemFisica;
use App\Services\Estoque\EstoqueQr;
use App\Services\Estoque\RastreioDoItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Contagem fisica pelo celular (e tambem no computador): escolhe a contagem aberta, o endereco (corredor/prateleira/nivel) e conta
 * item a item, sem papel. A contagem e' CEGA: quem conta nao ve o saldo do sistema, so' o que ele mesmo registrou. Cada registro
 * guarda quem contou, a data e a hora, e recontar nao apaga o anterior (fica no historico).
 */
#[Layout('layouts.checklist-mobile')]
class ContagemEstoqueMobile extends Component
{
    public ?string $contagemId = null;

    public ?string $enderecoId = null;

    public string $busca = '';

    /** @var array<string, string> item id => quantidade digitada */
    public array $quantidades = [];

    public ?string $aviso = null;

    public function mount(?string $contagem = null): void
    {
        abort_unless(Auth::user()?->can('viewAny', StockCount::class), 403);

        if ($contagem && StockCount::query()->whereKey($contagem)->exists()) {
            $this->contagemId = $contagem;
        }
    }

    public function abrirContagem(string $id): void
    {
        $this->contagemId = StockCount::query()->whereKey($id)->value('id');
        $this->enderecoId = null;
        $this->busca = '';
        $this->aviso = null;
    }

    public function abrirEndereco(?string $id): void
    {
        $this->enderecoId = $id;
        $this->quantidades = [];
        $this->aviso = null;
    }

    public function voltar(): void
    {
        if ($this->enderecoId !== null || $this->busca !== '') {
            $this->enderecoId = null;
            $this->busca = '';
        } else {
            $this->contagemId = null;
        }
        $this->aviso = null;
    }

    /**
     * Le o QR (ou codigo) de um item ou de uma prateleira: abre o endereco ou filtra pelo item.
     * Parafuso e outros itens minusculos: le o QR da caixa/saquinho/prateleira e digita a quantidade.
     */
    public function lerQr(string $texto): void
    {
        $lido = app(EstoqueQr::class)->interpretar($texto);
        $contagem = $this->contagemId ? StockCount::query()->find($this->contagemId) : null;

        if (! $lido || ! $contagem) {
            $this->aviso = 'Não reconheci esse código. Tente de novo ou busque pelo nome.';

            return;
        }

        $itens = $contagem->items();
        if ($lido['tipo'] === EstoqueQr::ENDERECO) {
            if (! (clone $itens)->where('stock_location_id', $lido['id'])->exists()) {
                $this->aviso = 'Esse endereço não tem itens nesta contagem.';

                return;
            }
            $this->abrirEndereco($lido['id']);

            return;
        }

        $tipoItem = $lido['tipo'] === RastreioDoItem::UNIDADE_AGREGADA ? StockPlacement::AGGREGATE : $lido['tipo'];
        $idItem = $lido['tipo'] === RastreioDoItem::UNIDADE_AGREGADA ? (string) AggregateItem::query()->whereKey($lido['id'])->value('aggregate_item_type_id') : $lido['id'];
        $achado = (clone $itens)->where('item_type', $tipoItem)->where('item_id', $idItem)->first();

        if (! $achado) {
            $this->aviso = 'Esse item não está nesta contagem.';

            return;
        }

        $this->enderecoId = null;
        $this->busca = $achado->item_sku ?: $achado->item_name;
        $this->aviso = null;
    }

    /** Registra a contagem de um item com o numero digitado. */
    public function registrar(string $itemId): void
    {
        $item = StockCountItem::query()->with('count')->find($itemId);
        $valor = str_replace(',', '.', trim((string) ($this->quantidades[$itemId] ?? '')));

        if (! $item || $valor === '' || ! is_numeric($valor)) {
            $this->aviso = 'Digite a quantidade contada (use 0 se não achou nada).';

            return;
        }

        try {
            abort_unless(Auth::user()?->can('update', $item->count), 403);
            app(ContagemFisica::class)->registrar($item, (float) $valor, Auth::user(), 'celular');
            unset($this->quantidades[$itemId]);
            $this->aviso = null;
        } catch (RuntimeException $e) {
            $this->aviso = $e->getMessage();
        }
    }

    public function render()
    {
        $contagens = collect();
        $contagem = null;
        $enderecos = collect();
        $itens = collect();

        if (! $this->contagemId) {
            $contagens = StockCount::query()->where('status', StockCount::EM_CONTAGEM)->with('internalUnit:id,name')->withCount([
                'items', 'items as contados_count' => fn ($q) => $q->whereNotNull('counted_quantity'),
            ])->latest()->get();
        } else {
            $contagem = StockCount::query()->with('internalUnit:id,name')->find($this->contagemId);
            $todos = $contagem ? $contagem->items()->get() : collect();

            if ($this->busca !== '') {
                $b = mb_strtolower($this->busca);
                $itens = $todos->filter(fn ($i) => str_contains(mb_strtolower($i->item_name.' '.$i->item_sku), $b))->values();
            } elseif ($this->enderecoId !== null) {
                $itens = $todos->where('stock_location_id', $this->enderecoId === '-' ? null : $this->enderecoId)->sortBy('item_name')->values();
            } else {
                $enderecos = $todos->groupBy(fn ($i) => $i->stock_location_id ?? '-')->map(fn ($g, $id) => [
                    'id' => (string) $id, 'codigo' => $g->first()->location_code ?: 'Sem endereço', 'total' => $g->count(), 'contados' => $g->whereNotNull('counted_quantity')->count(),
                ])->sortBy('codigo')->values();
            }
        }

        return view('livewire.contagem-estoque-mobile', compact('contagens', 'contagem', 'enderecos', 'itens'));
    }
}
