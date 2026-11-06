<?php

namespace App\Http\Controllers;

use App\Filament\Pages\RastreioDoItemPage;
use App\Filament\Resources\StockLocationResource;
use App\Models\AggregateItem;
use App\Models\StockLocation;
use App\Services\Estoque\EstoqueQr;
use App\Services\Estoque\EstoqueUnificado;
use Illuminate\Http\Request;

/** Link do QR do estoque (abre o item ou o endereco) e impressao das etiquetas. */
class EstoqueQrController extends Controller
{
    public function abrir(string $tipo, string $id)
    {
        abort_unless(in_array($tipo, EstoqueQr::TIPOS, true), 404);
        abort_unless(auth()->user()?->can('viewAny', StockLocation::class), 403);

        if ($tipo === EstoqueQr::ENDERECO) {
            abort_unless(StockLocation::query()->whereKey($id)->exists(), 404);

            return redirect(StockLocationResource::getUrl('edit', ['record' => $id]));
        }

        return redirect(RastreioDoItemPage::getUrl(['item' => $tipo.'|'.$id]));
    }

    /**
     * Folha de etiquetas para imprimir. tipo=itens (um QR por item; item agregado sai uma etiqueta por unidade) ou tipo=enderecos.
     * modelo: grande (prateleira/corredor), media (caixa/compartimento), pequena (saquinho, parafuso, arruela).
     */
    public function etiquetas(Request $request, EstoqueQr $qr, EstoqueUnificado $estoque)
    {
        abort_unless(auth()->user()?->can('viewAny', StockLocation::class), 403);

        $modelo = in_array($request->query('modelo'), ['grande', 'media', 'pequena'], true) ? $request->query('modelo') : 'media';
        $etiquetas = collect();

        if ($request->query('tipo') === 'enderecos') {
            StockLocation::query()->with('internalUnit:id,name')
                ->when($request->query('unidade'), fn ($q, $v) => $q->where('internal_unit_id', $v))
                ->when($request->query('corredor'), fn ($q, $v) => $q->where('aisle', $v))
                ->when($request->query('ids'), fn ($q, $v) => $q->whereIn('id', (array) $v))
                ->orderBy('code')->get()->each(function (StockLocation $e) use ($qr, $etiquetas) {
                    $etiquetas->push(['qr' => $qr->svg(EstoqueQr::ENDERECO, (string) $e->id, 220), 'titulo' => $e->code, 'linha1' => $e->internalUnit?->name, 'linha2' => $e->description, 'rodape' => 'ENDEREÇO']);
                });
        } else {
            $filtros = $request->only(['grupo', 'tipo_item', 'categoria', 'unidade', 'corredor', 'situacao', 'endereco', 'busca']);
            $filtros['tipo'] = $filtros['tipo_item'] ?? null;

            foreach ($estoque->itens($filtros) as $item) {
                if ($item['tipo'] === EstoqueUnificado::AGGREGATE) {
                    AggregateItem::query()->where('aggregate_item_type_id', $item['id'])->orderBy('code')->get(['id', 'code'])->each(function ($u) use ($qr, $item, $etiquetas) {
                        $etiquetas->push(['qr' => $qr->svg('aggregate_unit', (string) $u->id, 220), 'titulo' => $item['nome'], 'linha1' => 'Código '.$u->code, 'linha2' => $item['grupo'], 'rodape' => 'UNIDADE']);
                    });

                    continue;
                }
                $etiquetas->push([
                    'qr' => $qr->svg($item['tipo'], $item['id'], 220), 'titulo' => $item['nome'], 'linha1' => $item['sku'] ? 'Código '.$item['sku'] : null,
                    'linha2' => $item['grupo'].($item['enderecos'] ? ' · '.implode(', ', array_slice($item['enderecos'], 0, 2)) : ''), 'rodape' => $item['tipo_rotulo'],
                ]);
            }
        }

        return view('print.etiquetas-estoque', ['etiquetas' => $etiquetas->take(600), 'modelo' => $modelo, 'total' => $etiquetas->count()]);
    }
}
