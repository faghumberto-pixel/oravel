<?php

namespace App\Dashboards\Concerns;

use App\Models\MaintenanceOrder;
use Illuminate\Database\Eloquent\Builder;

/** Consultas comuns aos blocos de Manutenção. O escopo de tenant vem do próprio model (BelongsToTenant). */
trait OrdensDeServico
{
    /** Situações que encerram a OS (mesma lista usada em PainelSlaEmergencia). */
    protected function encerradas(): array
    {
        return ['Concluída', 'Completado', 'Cancelada'];
    }

    protected function concluidas(): array
    {
        return ['Concluída', 'Completado'];
    }

    protected function abertas(): Builder
    {
        return MaintenanceOrder::query()->whereNotIn('status', $this->encerradas());
    }

    protected function plural(int $n, string $singular, string $plural): string
    {
        return $n.' '.($n === 1 ? $singular : $plural);
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
