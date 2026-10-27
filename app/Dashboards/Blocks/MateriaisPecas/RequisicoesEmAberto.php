<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\MaterialRequest;

class RequisicoesEmAberto extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.requisicoes_abertas';
    }

    public static function title(): string
    {
        return 'Requisições em andamento';
    }

    public static function subtitle(): string
    {
        return 'Do pedido até a entrega';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_material_requests';
    }

    public static function sourceModel(): string
    {
        return MaterialRequest::class;
    }

    public function data(array $config): array
    {
        $porSituacao = MaterialRequest::query()
            ->whereIn('status', ['rascunho', 'aguardando_aprovacao', 'aprovado', 'cotado', 'a_caminho'])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $rotulos = ['rascunho' => 'Rascunho', 'aguardando_aprovacao' => 'Aguardando aprovação', 'aprovado' => 'Aprovada', 'cotado' => 'Cotada', 'a_caminho' => 'A caminho'];
        $linhas = [];
        foreach ($rotulos as $status => $rotulo) {
            $n = (int) ($porSituacao[$status] ?? 0);
            $linhas[] = [$rotulo, $n, (string) $n, $status === 'aguardando_aprovacao' && $n > 0 ? 'warn' : ''];
        }

        return Payload::bars($linhas, max(1, ...array_column($linhas, 1)));
    }
}
