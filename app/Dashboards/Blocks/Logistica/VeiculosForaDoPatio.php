<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\Asset;
use App\Models\FrotaSaidaVeiculo;

class VeiculosForaDoPatio extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.veiculos_fora';
    }

    public static function title(): string
    {
        return 'Veículos fora do pátio';
    }

    public static function subtitle(): string
    {
        return 'Saídas sem retorno registrado';
    }

    public static function size(): int
    {
        return 12;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_saidas_veiculo';
    }

    public static function sourceModel(): string
    {
        return FrotaSaidaVeiculo::class;
    }

    public function data(array $config): array
    {
        $saidas = FrotaSaidaVeiculo::query()->fora()->orderBy('saida_em')->limit(6)->get(['ativo_id', 'condutor_nome', 'destino', 'saida_em']);
        $nomes = Asset::query()->whereIn('id', $saidas->pluck('ativo_id'))->pluck('name', 'id');

        $linhas = $saidas->map(function ($s) use ($nomes) {
            $horas = (int) $s->saida_em->diffInHours(now());

            return [$nomes[$s->ativo_id] ?? '—', $s->destino, $s->condutor_nome ?: '—', $horas >= FrotaSaidaVeiculo::AVISO_FORA_HORAS ? Payload::pill('crit', $horas.' h') : Payload::pill('n', $horas.' h')];
        })->all();

        return Payload::table(['Veículo', 'Destino', 'Condutor', '>Fora há'], $linhas, 'Todos os veículos estão no pátio.');
    }
}
