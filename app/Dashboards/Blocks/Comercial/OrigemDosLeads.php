<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\CrmLead;

class OrigemDosLeads extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.origem_leads';
    }

    public static function title(): string
    {
        return 'De onde vêm os leads';
    }

    public static function subtitle(): string
    {
        return 'Origem dos contatos do funil';
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
        $porOrigem = CrmLead::query()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source');
        $cores = ['indicacao' => '#16a34a', 'site' => '#38bdf8', 'contato_frio' => '#f59e0b', 'evento' => '#a78bfa', 'outro' => '#94a3b8'];

        $fatias = collect($this->origens())->map(fn ($rotulo, $slug) => [$rotulo, (int) ($porOrigem[$slug] ?? 0), $cores[$slug]])->filter(fn ($f) => $f[1] > 0)->sortByDesc(fn ($f) => $f[1])->values()->all();

        return Payload::donut($fatias, (string) $porOrigem->sum(), 'leads');
    }
}
