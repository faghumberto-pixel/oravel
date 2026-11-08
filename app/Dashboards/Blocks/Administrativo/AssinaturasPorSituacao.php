<?php

namespace App\Dashboards\Blocks\Administrativo;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\DocumentSignature;

class AssinaturasPorSituacao extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'administrativo.assinaturas_situacao';
    }

    public static function title(): string
    {
        return 'Assinaturas por situação';
    }

    public static function subtitle(): string
    {
        return 'Pendentes, assinadas, vencidas e canceladas';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function module(): string
    {
        return 'Adicionais';
    }

    public static function requiredFeature(): string
    {
        return 'assinatura_eletronica';
    }

    public static function sourceModel(): string
    {
        return DocumentSignature::class;
    }

    public function data(array $config): array
    {
        $por = DocumentSignature::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $rotulos = ['pending' => 'Pendentes', 'signed' => 'Assinadas', 'expired' => 'Vencidas', 'canceled' => 'Canceladas'];
        $valores = [];

        foreach ($rotulos as $status => $rotulo) {
            $valores[$rotulo] = (int) ($por[$status] ?? 0);
        }

        return Payload::donut($this->fatias($valores, ['Pendentes' => '#f59e0b', 'Assinadas' => '#16a34a', 'Vencidas' => '#ef4444', 'Canceladas' => '#94a3b8']), (string) array_sum($valores), 'documentos');
    }
}
