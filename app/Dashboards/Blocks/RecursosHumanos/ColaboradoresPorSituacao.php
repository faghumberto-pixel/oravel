<?php

namespace App\Dashboards\Blocks\RecursosHumanos;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Employee;

class ColaboradoresPorSituacao extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'rh.por_situacao';
    }

    public static function title(): string
    {
        return 'Colaboradores por situação';
    }

    public static function subtitle(): string
    {
        return 'Ativos, afastados, desligados e cadastros incompletos';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function module(): string
    {
        return 'Equipe';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_employees';
    }

    public static function sourceModel(): string
    {
        return Employee::class;
    }

    public function data(array $config): array
    {
        $por = Employee::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $rotulos = Employee::statusLabels();
        $valores = [];

        foreach ($rotulos as $status => $rotulo) {
            $valores[$rotulo] = (int) ($por[$status] ?? 0);
        }

        return Payload::donut($this->fatias($valores, [$rotulos[Employee::STATUS_ATIVO] => '#16a34a', $rotulos[Employee::STATUS_DESLIGADO] => '#94a3b8']), (string) array_sum($valores), 'pessoas');
    }
}
