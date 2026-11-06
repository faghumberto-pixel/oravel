<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Services\LocalizacaoEquipamentosService;

class Localizados extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.localizados';
    }

    public static function title(): string
    {
        return 'Equipamentos localizados';
    }

    public static function subtitle(): string
    {
        return 'Com posição conhecida (rastreador, obra do contrato ou cadastro)';
    }

    public static function chaveDoContrato(): string
    {
        return LocalizacaoEquipamentosService::FEATURE;
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $r = app(LocalizacaoEquipamentosService::class)->pontos();
        $pontos = collect($r['pontos']);
        $total = $pontos->count() + $r['sem_localizacao'];
        $pct = $total > 0 ? (int) round($pontos->count() / $total * 100) : 0;

        return Payload::kpi(
            $pontos->count(),
            'de '.$total,
            $pontos->where('fonte', 'contrato')->count().' em obra · '.$pontos->where('rastreador', true)->count().' com rastreador',
            'n',
            ['pct' => $pct, 'tone' => $pct === 100 ? 'ok' : 'warn'],
        );
    }
}
