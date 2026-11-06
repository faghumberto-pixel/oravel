<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\Asset;
use App\Models\FrotaSaidaVeiculo;

class NaRuaAgora extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.na_rua_agora';
    }

    public static function title(): string
    {
        return 'Na rua agora';
    }

    public static function subtitle(): string
    {
        return 'Linha do tempo das saídas em aberto: quem levou, para onde e há quanto tempo';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $eventos = $this->naRua()->map(function ($s) {
            $horas = $this->horasFora($s);
            $longe = $horas >= FrotaSaidaVeiculo::AVISO_FORA_HORAS;

            return [
                $s->saida_em->format('d/m H\hi'), $s->destino.' — '.(Asset::withoutGlobalScopes()->whereKey($s->ativo_id)->value('name') ?? 'veículo'),
                'Com '.($s->condutor_nome ?: 'motorista não informado').' · '.($this->finalidades()[$s->finalidade] ?? $s->finalidade).' · fora há '.$horas.' h',
                $longe ? 'crit' : 'warn', $longe ? 'fora há muito tempo' : 'na rua',
            ];
        })->values()->all();

        return Payload::timeline($eventos, 'Todos os veículos estão no pátio.');
    }
}
