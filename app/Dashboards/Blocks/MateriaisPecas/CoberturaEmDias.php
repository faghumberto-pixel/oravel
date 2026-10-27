<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;

class CoberturaEmDias extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.cobertura_dias';
    }

    public static function title(): string
    {
        return 'Cobertura de materiais';
    }

    public static function subtitle(): string
    {
        return 'Dias de estoque pelo consumo dos últimos 90 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function defaultConfig(): array
    {
        return ['dias_critico' => 10, 'dias_atencao' => 20];
    }

    public function data(array $config): array
    {
        $janela = 90;
        $consumo = $this->saidasPorMaterial($janela)->filter(fn ($q) => $q > 0);
        $materiais = Material::query()->whereIn('id', $consumo->keys())->get(['id', 'name', 'current_stock']);

        $linhas = $materiais
            ->map(fn ($m) => ['nome' => $m->name, 'dias' => (int) floor($m->current_stock / ($consumo[$m->id] / $janela))])
            ->sortBy('dias')
            ->take(5)
            ->map(fn ($r) => [
                $r['nome'],
                $r['dias'],
                $r['dias'].' dias',
                $r['dias'] <= ($config['dias_critico'] ?? 10) ? 'crit' : ($r['dias'] <= ($config['dias_atencao'] ?? 20) ? 'warn' : 'ok'),
            ])
            ->values()
            ->all();

        return Payload::bars($linhas, max(30, ...array_column($linhas, 1) ?: [30]));
    }
}
