<?php

namespace App\Dashboards\Blocks\Administrativo;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\DocumentSignature;

class AssinaturasPendentes extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'administrativo.assinaturas_pendentes';
    }

    public static function title(): string
    {
        return 'Assinaturas pendentes';
    }

    public static function subtitle(): string
    {
        return 'Documentos esperando assinatura';
    }

    public static function size(): int
    {
        return 3;
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
        $pendentes = DocumentSignature::query()->where('status', 'pending');
        $total = (clone $pendentes)->count();
        $vencidas = (clone $pendentes)->whereNotNull('expires_at')->where('expires_at', '<', now())->count();

        return Payload::kpi($total, $total === 1 ? 'documento' : 'documentos', $vencidas > 0 ? $vencidas.' com o prazo vencido' : 'nenhum com prazo vencido', $vencidas > 0 ? 'dn' : 'up', null, $this->serieMensal(DocumentSignature::query(), 'created_at', 6));
    }
}
