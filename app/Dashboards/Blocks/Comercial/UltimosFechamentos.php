<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\CrmLead;
use Illuminate\Support\Carbon;

class UltimosFechamentos extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.ultimos_fechamentos';
    }

    public static function title(): string
    {
        return 'Últimos fechamentos';
    }

    public static function subtitle(): string
    {
        return 'Negócios ganhos, do mais recente ao mais antigo';
    }

    public static function size(): int
    {
        return 6;
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
        $ganhos = CrmLead::query()->where('stage', CrmLead::STAGE_CONVERTIDO)->whereNotNull('fechado_em')->orderByDesc('fechado_em')->limit(7)->get();
        $vendedores = $this->nomesDeUsuarios($ganhos->pluck('vendedor_fechamento_id'));

        return Payload::timeline(
            $ganhos->map(fn ($l) => [
                Carbon::parse($l->fechado_em)->format('d/m'), $l->company_name ?: $l->name,
                $this->compacto((float) $l->valor_fechado).' · '.($vendedores[$l->vendedor_fechamento_id] ?? 'sem vendedor').($l->equipment_interest ? ' · '.$l->equipment_interest : ''), 'ok', 'fechado',
            ])->all(),
            'Nenhum negócio fechado ainda.',
        );
    }
}
