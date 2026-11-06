<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Models\Contract;
use Illuminate\Support\Carbon;

class DevolucoesPrevistas extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.devolucoes';
    }

    public static function title(): string
    {
        return 'Devoluções previstas';
    }

    public static function subtitle(): string
    {
        return 'Equipamentos que voltam nos próximos 30 dias, em ordem de data';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $contratos = Contract::query()->with(['asset:id,name,patrimonio', 'client:id,name'])->where('status', 'Ativo')
            ->whereNotNull('end_date')->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays(30)])->orderBy('end_date')->limit(8)->get();

        $eventos = $contratos->map(function ($c) {
            $dias = (int) Carbon::today()->diffInDays($c->end_date);

            return [
                Carbon::parse($c->end_date)->format('d/m'),
                trim(($c->asset?->patrimonio ? $c->asset->patrimonio.' · ' : '').($c->asset?->name ?? 'Equipamento')),
                ($c->client?->name ?? 'Cliente').' · '.($dias === 0 ? 'hoje' : 'em '.$dias.' dias'),
                $dias <= 7 ? 'warn' : 'n', $dias <= 7 ? 'esta semana' : null,
            ];
        })->all();

        return Payload::timeline($eventos, 'Nenhuma devolução prevista para os próximos 30 dias.');
    }
}
