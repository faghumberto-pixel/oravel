<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Contract;
use App\Models\CrmLead;
use App\Models\PropostaComercial;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

class ProximosCompromissos extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.proximos_compromissos';
    }

    public static function title(): string
    {
        return 'Próximos compromissos';
    }

    public static function subtitle(): string
    {
        return 'Retornos de leads, fim de locações e propostas que vencem, em ordem de data';
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
        $hoje = Carbon::today();
        $tenant = Tenancy::current();
        $eventos = [];

        $leads = CrmLead::query()->whereNotIn('stage', [CrmLead::STAGE_CONVERTIDO, CrmLead::STAGE_PERDIDO])
            ->whereBetween('next_followup_date', [$hoje->copy()->subDays(7)->toDateString(), $hoje->copy()->addDays(14)->toDateString()])->get();
        $vendedores = $this->nomesDeUsuarios($leads->pluck('assigned_user_id'));
        foreach ($leads as $l) {
            $data = Carbon::parse($l->next_followup_date);
            $atrasado = $data->lt($hoje);
            $eventos[] = [$data, 'Retornar para '.($l->company_name ?: $l->name), ($this->etapas()[$l->stage] ?? $l->stage).' · '.$this->compacto((float) $l->estimated_value).' · '.($vendedores[$l->assigned_user_id] ?? 'sem vendedor'),
                $atrasado ? 'crit' : ($data->isSameDay($hoje) ? 'warn' : 'a'), $atrasado ? 'atrasado' : null];
        }

        if ($tenant?->hasFeature('tabela_contracts')) {
            $contratos = Contract::query()->with('client:id,name')->where('status', 'Ativo')->whereBetween('end_date', [$hoje->toDateString(), $hoje->copy()->addDays(30)->toDateString()])->get();
            foreach ($contratos as $c) {
                $eventos[] = [Carbon::parse($c->end_date), 'Fim da locação — '.($c->client?->name ?? 'cliente'), 'Contrato '.$c->contract_number.': hora de renovar ou programar a devolução', 'warn', 'renovar'];
            }
        }

        if ($tenant?->hasFeature('tabela_proposta_comercial')) {
            $propostas = PropostaComercial::query()->with('client:id,name')->whereIn('status', [PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, PropostaComercial::STATUS_APROVADA_INTERNA])
                ->whereBetween('valid_until', [$hoje->toDateString(), $hoje->copy()->addDays(10)->toDateString()])->get();
            foreach ($propostas as $p) {
                $eventos[] = [Carbon::parse($p->valid_until), 'Proposta vence — '.($p->client?->name ?? 'cliente'), $this->compacto((float) $p->total_value).' aguardando resposta', 'warn', 'validade'];
            }
        }

        usort($eventos, fn ($a, $b) => $a[0] <=> $b[0]);

        return Payload::timeline(
            array_map(fn ($e) => [$this->rotuloDaData($e[0]), $e[1], $e[2], $e[3], $e[4]], array_slice($eventos, 0, 8)),
            'Nenhum compromisso nos próximos dias.',
        );
    }
}
