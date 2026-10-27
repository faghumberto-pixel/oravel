<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Block;
use App\Models\Asset;

/** Base dos blocos de Ativos (equipamentos e veículos da empresa). */
abstract class AtivosBlock extends Block
{
    public static function module(): string
    {
        return 'Ativos';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_assets';
    }

    public static function sourceModel(): string
    {
        return Asset::class;
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function rotuloDaSituacao(?string $status): string
    {
        return match ($status) {
            Asset::STATUS_DISPONIVEL => 'Disponível',
            Asset::STATUS_LOCADO => 'Locado',
            Asset::STATUS_MANUTENCAO => 'Em manutenção',
            Asset::STATUS_OPERANDO => 'Em operação',
            Asset::STATUS_AGUARDANDO_TRIAGEM => 'Aguardando triagem',
            Asset::STATUS_QUARENTENA => 'Quarentena',
            Asset::STATUS_RESERVADO => 'Reservado',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Sem situação',
        };
    }
}
