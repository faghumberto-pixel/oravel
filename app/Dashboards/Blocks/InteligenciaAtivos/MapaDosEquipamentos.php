<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Filament\Pages\LocalizacaoEquipamentos;
use App\Services\LocalizacaoEquipamentosService;

class MapaDosEquipamentos extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.mapa';
    }

    public static function title(): string
    {
        return 'Onde estão os equipamentos';
    }

    public static function subtitle(): string
    {
        return 'Cada ponto é um equipamento; a cor é a situação';
    }

    public static function chaveDoContrato(): string
    {
        return LocalizacaoEquipamentosService::FEATURE;
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $pontos = collect(app(LocalizacaoEquipamentosService::class)->pontos()['pontos']);

        $mapa = $pontos->map(fn ($p) => [
            $p['lat'], $p['lng'], self::STATUS_CORES[$p['status']] ?? '#6b7280',
            trim(($p['patrimonio'] ? $p['patrimonio'].' · ' : '').$p['nome']),
            $p['status_rotulo'].($p['cliente'] ? ' · '.$p['cliente'] : '').' · '.$p['local'],
        ])->all();

        $legenda = $pontos->unique('status')->map(fn ($p) => [$p['status_rotulo'], self::STATUS_CORES[$p['status']] ?? '#6b7280'])->values()->all();

        return Payload::map($mapa, $legenda, LocalizacaoEquipamentos::getUrl(), 'Abrir o mapa completo');
    }
}
