<?php

namespace App\Dashboards\Blocks\RecursosHumanos;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\EpiDelivery;

class EpiADevolver extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'rh.epi_a_devolver';
    }

    public static function title(): string
    {
        return 'EPI com devolução prevista';
    }

    public static function subtitle(): string
    {
        return 'Entregues e com data de devolução próxima ou vencida';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function module(): string
    {
        return 'EPI';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_epi_deliveries';
    }

    public static function sourceModel(): string
    {
        return EpiDelivery::class;
    }

    public function data(array $config): array
    {
        $linhas = EpiDelivery::query()->with(['employee:id,name', 'material:id,name'])->where('status', EpiDelivery::STATUS_ATIVO)
            ->whereNotNull('expected_return_at')->whereDate('expected_return_at', '<=', now()->addDays(30)->toDateString())
            ->orderBy('expected_return_at')->limit(8)->get()->map(function (EpiDelivery $d) {
                $dias = (int) now()->startOfDay()->diffInDays($d->expected_return_at->startOfDay(), false);

                return [
                    $d->employee?->name ?? '—', $d->material?->name ?? '—', $d->expected_return_at->format('d/m/Y'),
                    $dias < 0 ? Payload::pill('crit', 'atrasado '.abs($dias).' d') : Payload::pill($dias <= 7 ? 'warn' : 'ok', 'em '.$dias.' d'),
                ];
            })->all();

        return Payload::table(['Colaborador', 'EPI', '>Devolver em', '>Situação'], $linhas, 'Nenhuma devolução próxima.');
    }
}
