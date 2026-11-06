<?php

namespace App\Services\Estoque;

use App\Models\AggregateItem;
use App\Models\Material;
use App\Models\Part;
use App\Models\StockLocation;
use App\Models\StockPlacement;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR do estoque. Duas ideias, para valer tambem para item minusculo (parafuso, arruela):
 *  - o codigo do ITEM (SKU) vai na caixa, no saquinho ou no compartimento, nunca em cada unidade;
 *  - o codigo do ENDERECO (corredor/prateleira/nivel) vai na prateleira.
 * Unidade individual so' tem QR proprio quando e' um item durável com código (item agregado, ferramenta, bateria).
 * O QR guarda um link (/admin/estoque/qr/{tipo}/{id}): a camera do celular abre o item; o app de contagem le o mesmo QR.
 */
class EstoqueQr
{
    public const ENDERECO = 'endereco';

    public const TIPOS = [StockPlacement::MATERIAL, StockPlacement::PART, StockPlacement::AGGREGATE, RastreioDoItem::UNIDADE_AGREGADA, self::ENDERECO];

    public function link(string $tipo, string $id): string
    {
        return route('estoque.qr', ['tipo' => $tipo, 'id' => $id]);
    }

    public function svg(string $tipo, string $id, int $tamanho = 160): string
    {
        return (string) QrCode::format('svg')->size($tamanho)->margin(0)->errorCorrection('M')->generate($this->link($tipo, $id));
    }

    /**
     * Entende o que foi lido: o link do QR deste sistema, ou um codigo solto (SKU, codigo de barras, codigo da unidade).
     *
     * @return array{tipo: string, id: string}|null
     */
    public function interpretar(string $lido): ?array
    {
        $lido = trim($lido);
        if ($lido === '') {
            return null;
        }

        if (preg_match('#/estoque/qr/([a-z_]+)/([0-9a-fA-F-]+)#', $lido, $m) && in_array($m[1], self::TIPOS, true)) {
            return ['tipo' => $m[1], 'id' => $m[2]];
        }

        if ($material = Material::query()->where('sku', $lido)->orWhere('barcode', $lido)->first(['id'])) {
            return ['tipo' => StockPlacement::MATERIAL, 'id' => (string) $material->id];
        }
        if ($peca = Part::query()->where('sku', $lido)->orWhere('barcode', $lido)->first(['id'])) {
            return ['tipo' => StockPlacement::PART, 'id' => (string) $peca->id];
        }
        if ($unidade = AggregateItem::query()->where('code', $lido)->first(['id'])) {
            return ['tipo' => RastreioDoItem::UNIDADE_AGREGADA, 'id' => (string) $unidade->id];
        }
        if ($endereco = StockLocation::query()->where('code', $lido)->first(['id'])) {
            return ['tipo' => self::ENDERECO, 'id' => (string) $endereco->id];
        }

        return null;
    }
}
