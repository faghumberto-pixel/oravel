<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Quote;

class OrcamentosSemRetorno extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.orcamentos_sem_retorno';
    }

    public static function title(): string
    {
        return 'Orçamentos sem retorno';
    }

    public static function subtitle(): string
    {
        return 'Enviados há mais de 5 dias';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function defaultConfig(): array
    {
        return ['dias' => 5];
    }

    public static function requiredFeature(): string
    {
        return 'tabela_quotes';
    }

    public static function sourceModel(): string
    {
        return Quote::class;
    }

    public function data(array $config): array
    {
        $parados = Quote::query()
            ->where('status', Quote::STATUS_ENVIADO)
            ->whereNull('client_responded_at')
            ->where('sent_at', '<', now()->subDays((int) ($config['dias'] ?? 5)));

        $n = (clone $parados)->count();

        return Payload::kpi($n, '', $n > 0 ? $this->brl((float) (clone $parados)->sum('total_value')).' parados' : 'Nenhum orçamento parado', $n > 0 ? 'dn' : 'n');
    }
}
