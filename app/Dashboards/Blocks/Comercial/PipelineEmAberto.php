<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\CrmLead;
use Illuminate\Support\Carbon;

class PipelineEmAberto extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.pipeline';
    }

    public static function title(): string
    {
        return 'Oportunidades em aberto';
    }

    public static function subtitle(): string
    {
        return 'Valor estimado no funil, com novos leads por mês';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_leads';
    }

    public static function sourceModel(): string
    {
        return CrmLead::class;
    }

    public function data(array $config): array
    {
        $abertas = CrmLead::query()->whereNotIn('stage', [CrmLead::STAGE_CONVERTIDO, CrmLead::STAGE_PERDIDO]);
        $n = (clone $abertas)->count();
        $valor = (float) (clone $abertas)->sum('estimated_value');
        $em30 = (float) (clone $abertas)->whereBetween('previsao_fechamento', [Carbon::today()->toDateString(), Carbon::today()->addDays(30)->toDateString()])->sum('estimated_value');

        $inicio = Carbon::now()->startOfMonth()->subMonths(11);
        $porMes = CrmLead::query()->where('created_at', '>=', $inicio)->get(['created_at'])->groupBy(fn ($l) => $l->created_at->format('Y-m'))->map->count();
        $serie = collect(range(0, 11))->map(fn ($i) => (int) ($porMes[$inicio->copy()->addMonths($i)->format('Y-m')] ?? 0))->all();

        return Payload::kpi($this->compacto($valor), '', $n.' oportunidades · '.$this->compacto($em30).' previstos para 30 dias', 'n', null, $serie);
    }
}
