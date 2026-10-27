<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Support\Carbon;

class ClientesParaLigar extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.clientes_para_ligar';
    }

    public static function title(): string
    {
        return 'Clientes para ligar';
    }

    public static function subtitle(): string
    {
        return 'Já locaram e estão sem contrato novo há 90 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function defaultConfig(): array
    {
        return ['dias' => 90];
    }

    public function data(array $config): array
    {
        $dias = (int) ($config['dias'] ?? 90);

        $ultimos = Contract::query()
            ->whereNotNull('start_date')
            ->selectRaw('client_id, max(start_date) as ultimo, sum(case when status = ? then 1 else 0 end) as ativos', ['Ativo'])
            ->groupBy('client_id')
            ->havingRaw('sum(case when status = ? then 1 else 0 end) = 0', ['Ativo'])
            ->havingRaw('max(start_date) < ?', [now()->subDays($dias)->toDateString()])
            ->orderBy('ultimo')
            ->limit(6)
            ->get();

        $nomes = Client::query()->whereIn('id', $ultimos->pluck('client_id'))->pluck('name', 'id');

        $linhas = $ultimos->map(fn ($c) => [
            $nomes[$c->client_id] ?? '—',
            Carbon::parse($c->ultimo)->format('d/m/Y'),
            (string) (int) Carbon::parse($c->ultimo)->diffInDays(now()).' dias',
        ])->all();

        return Payload::table(['Cliente', 'Último contrato', '>Há'], $linhas, 'Nenhum cliente parado há mais de '.$dias.' dias.');
    }
}
