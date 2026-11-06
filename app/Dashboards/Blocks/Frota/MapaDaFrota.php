<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\Client;
use App\Models\InternalUnit;

class MapaDaFrota extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.mapa_frota';
    }

    public static function title(): string
    {
        return 'Mapa da frota';
    }

    public static function subtitle(): string
    {
        return 'Verde no pátio, âmbar na rua, vermelho parado por sinistro';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $fora = $this->naRua()->groupBy(fn ($s) => (string) $s->ativo_id)->map->last();
        $parados = $this->paradosPorSinistro();
        $titulares = $this->titulares();
        $unidades = InternalUnit::query()->orderBy('created_at')->get(['id', 'name', 'latitude', 'longitude'])->keyBy('id');
        $clientes = Client::query()->whereIn('id', $fora->pluck('cliente_id')->filter())->get(['id', 'name', 'latitude', 'longitude'])->keyBy('id');

        $pontos = [];
        $vistos = [];
        foreach ($this->veiculos() as $i => $v) {
            $id = (string) $v->id;
            $saida = $fora->get($id);
            $cliente = $saida ? $clientes->get($saida->cliente_id) : null;
            $unidade = $this->unidadeDoPatio($v, $unidades);
            if ($saida && $cliente && $cliente->latitude !== null) {
                [$lat, $lng, $cor, $onde] = [(float) $cliente->latitude + 0.004, (float) $cliente->longitude - 0.004, '#f59e0b', 'Na rua · '.$saida->destino.' · com '.($saida->condutor_nome ?: 'motorista não informado')];
            } elseif ($unidade && $unidade->latitude !== null) {
                $jit = ($i - 2.5) * 0.0011;
                [$lat, $lng, $cor, $onde] = [(float) $unidade->latitude + $jit, (float) $unidade->longitude + (($i % 2) ? 0.0012 : -0.0012), isset($parados[$id]) ? '#ef4444' : '#16a34a', (isset($parados[$id]) ? 'Parado · sinistro' : 'No pátio').' · '.$unidade->name.' · titular: '.($titulares[$id] ?? 'sem titular')];
            } else {
                continue;
            }
            $pontos[] = [$lat, $lng, $cor, trim(($v->placa ? $v->placa.' · ' : '').$v->name), $onde];
            $vistos[$cor] = $cor === '#f59e0b' ? 'Na rua' : ($cor === '#ef4444' ? 'Parado' : 'No pátio');
        }

        return Payload::map($pontos, collect($vistos)->map(fn ($rotulo, $cor) => [$rotulo, $cor])->values()->all());
    }
}
