<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\CrmLead;

class OrigemPorMes extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.origem_x_mes';
    }

    public static function title(): string
    {
        return 'Leads por origem e mês';
    }

    public static function subtitle(): string
    {
        return 'Mapa de calor dos últimos 6 meses: onde o esforço comercial rende contatos';
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
        $inicio = now()->startOfMonth()->subMonths(5);
        $leads = CrmLead::query()->where('created_at', '>=', $inicio)->get(['source', 'created_at']);
        $colunas = [];
        for ($i = 0; $i < 6; $i++) {
            $colunas[] = ucfirst($inicio->copy()->addMonths($i)->translatedFormat('M'));
        }

        $linhas = [];
        foreach ($this->origens() as $slug => $rotulo) {
            $valores = [];
            for ($i = 0; $i < 6; $i++) {
                $mes = $inicio->copy()->addMonths($i);
                $valores[] = $leads->filter(fn ($l) => $l->source === $slug && $l->created_at->isSameMonth($mes))->count();
            }
            $linhas[] = [$rotulo, '', $valores, (string) array_sum($valores).' leads', null];
        }

        return Payload::heat($colunas, $linhas, '', 'Mais leads no mês');
    }
}
