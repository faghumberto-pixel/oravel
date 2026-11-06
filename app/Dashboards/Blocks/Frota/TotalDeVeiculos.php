<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;

class TotalDeVeiculos extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.total_veiculos';
    }

    public static function title(): string
    {
        return 'Veículos da frota';
    }

    public static function subtitle(): string
    {
        return 'Quantos são, quantos estão na rua e quantos no pátio';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $total = $this->veiculos()->count();
        $naRua = $this->naRua()->pluck('ativo_id')->unique()->count();
        $titulares = $this->titulares();
        $semTitular = $this->veiculos()->filter(fn ($v) => ! isset($titulares[(string) $v->id]))->count();
        $pct = $total > 0 ? (int) round($naRua / $total * 100) : 0;

        return Payload::kpi($total, $total === 1 ? 'veículo' : 'veículos', $naRua.' na rua · '.($total - $naRua).' no pátio'.($semTitular > 0 ? ' · '.$semTitular.' sem titular' : ''), 'n', ['pct' => $pct, 'tone' => 'warn']);
    }
}
