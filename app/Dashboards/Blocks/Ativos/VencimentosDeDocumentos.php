<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class VencimentosDeDocumentos extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.vencimentos';
    }

    public static function title(): string
    {
        return 'Documentos a vencer';
    }

    public static function subtitle(): string
    {
        return 'Licenciamento, seguro, IPVA e tacógrafo, em até 30 dias';
    }

    public static function size(): int
    {
        return 12;
    }

    public static function defaultConfig(): array
    {
        return ['dias' => 30, 'limite' => 8];
    }

    public function data(array $config): array
    {
        $limite = now()->addDays((int) ($config['dias'] ?? 30))->toDateString();
        $documentos = ['licenciamento_vencimento' => 'Licenciamento', 'seguro_vencimento' => 'Seguro', 'ipva_vencimento' => 'IPVA', 'tacografo_vencimento' => 'Tacógrafo'];

        $ativos = Asset::query()
            ->where(function ($q) use ($documentos, $limite) {
                foreach (array_keys($documentos) as $coluna) {
                    $q->orWhere($coluna, '<=', $limite);
                }
            })
            ->get(['id', 'name', 'placa', ...array_keys($documentos)]);

        $linhas = collect();
        foreach ($ativos as $ativo) {
            foreach ($documentos as $coluna => $rotulo) {
                if ($ativo->{$coluna} && $ativo->{$coluna}->toDateString() <= $limite) {
                    $linhas->push(['ativo' => $ativo, 'documento' => $rotulo, 'data' => $ativo->{$coluna}]);
                }
            }
        }

        $linhas = $linhas->sortBy('data')->take((int) ($config['limite'] ?? 8))->map(function ($l) {
            $dias = (int) now()->startOfDay()->diffInDays($l['data'], false);

            return [
                $l['ativo']->name,
                $l['ativo']->placa ?: '—',
                $l['documento'],
                $l['data']->format('d/m/Y'),
                $dias < 0 ? Payload::pill('crit', 'Vencido há '.abs($dias).' dias') : Payload::pill('warn', 'Em '.$dias.' dias'),
            ];
        })->values()->all();

        return Payload::table(['Ativo', 'Placa', 'Documento', 'Vencimento', 'Situação'], $linhas, 'Nenhum documento vencendo nos próximos '.(int) ($config['dias'] ?? 30).' dias.');
    }
}
