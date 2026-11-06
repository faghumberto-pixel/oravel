<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

class AlertasDeAtivos extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.alertas';
    }

    public static function title(): string
    {
        return 'O que pede atenção';
    }

    public static function subtitle(): string
    {
        return 'Custo alto, locações acabando e equipamentos parados na oficina';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $itens = [];
        $linhas = $this->ranking();

        foreach ($linhas->filter(fn ($l) => $l['situacao']['chave'] === 'trocar_vender')->sortByDesc('razao')->take(3) as $l) {
            $itens[] = ['Trocar/vender', 'crit', $this->nomeCurto($l).' já custou '.round($l['razao'] * 100).'% do valor ('.$this->compacto($l['custo_total']).' de '.$this->compacto($l['valor']).').'];
        }
        foreach ($linhas->filter(fn ($l) => $l['situacao']['chave'] === 'revisar')->sortByDesc('razao')->take(2) as $l) {
            $itens[] = ['Revisar', 'warn', $this->nomeCurto($l).' está em '.round($l['razao'] * 100).'% do valor: vale revisar o equipamento.'];
        }

        $parados = MaintenanceOrder::query()->with('asset:id,name,patrimonio')
            ->whereNotIn('status', ['Concluída', 'Completado', 'Cancelada'])->where('created_at', '<', Carbon::now()->subDays(7))
            ->orderBy('created_at')->limit(3)->get();
        foreach ($parados as $os) {
            $itens[] = ['Parado', 'crit', trim(($os->asset?->patrimonio ? $os->asset->patrimonio.' · ' : '').($os->asset?->name ?? 'Equipamento')).' está em manutenção há '.(int) $os->created_at->diffInDays(Carbon::now()).' dias.'];
        }

        $vencendo = Contract::query()->with(['asset:id,name,patrimonio', 'client:id,name'])->where('status', 'Ativo')
            ->whereNotNull('end_date')->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays(15)])->orderBy('end_date')->limit(3)->get();
        foreach ($vencendo as $c) {
            $dias = (int) Carbon::today()->diffInDays($c->end_date);
            $itens[] = ['Vence em '.$dias.'d', 'warn', 'Locação de '.trim(($c->asset?->patrimonio ? $c->asset->patrimonio.' · ' : '').($c->asset?->name ?? 'equipamento')).' ('.($c->client?->name ?? 'cliente').') termina em '.Carbon::parse($c->end_date)->format('d/m').'.'];
        }

        return Payload::alerts(array_slice($itens, 0, 8), 'Nenhum alerta: a frota está sob controle.');
    }
}
