<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;
use App\Models\InternalUnit;

class OndeEstaoOsVeiculos extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.onde_estao';
    }

    public static function title(): string
    {
        return 'Onde estão os veículos e com quem';
    }

    public static function subtitle(): string
    {
        return 'Cada veículo da frota: quem está com ele, onde está e desde quando';
    }

    public static function size(): int
    {
        return 12;
    }

    public function data(array $config): array
    {
        $fora = $this->naRua()->groupBy(fn ($s) => (string) $s->ativo_id)->map->last();
        $titulares = $this->titulares();
        $parados = $this->paradosPorSinistro();
        $unidades = InternalUnit::query()->orderBy('created_at')->get(['id', 'name'])->keyBy('id');

        $linhas = $this->veiculos()->map(function ($v) use ($fora, $titulares, $parados, $unidades) {
            $id = (string) $v->id;
            $saida = $fora->get($id);
            if ($saida) {
                $horas = $this->horasFora($saida);

                return [$v->name, $v->placa ?: '—', $saida->condutor_nome ?: ($titulares[$id] ?? '—'), $saida->destino, $horas >= FrotaSaidaVeiculo::AVISO_FORA_HORAS ? Payload::pill('crit', 'Na rua · '.$horas.' h') : Payload::pill('warn', 'Na rua · '.$horas.' h'), $saida->saida_em->format('d/m H\hi')];
            }
            if (isset($parados[$id])) {
                return [$v->name, $v->placa ?: '—', $titulares[$id] ?? 'Sem titular', ($this->unidadeDoPatio($v, $unidades)?->name ?? 'Pátio').' · oficina', Payload::pill('crit', 'Parado · sinistro'), '—'];
            }

            return [$v->name, $v->placa ?: '—', $titulares[$id] ?? 'Sem titular', $this->unidadeDoPatio($v, $unidades)?->name ?? 'Pátio', Payload::pill('ok', 'No pátio'), '—'];
        })->all();

        return Payload::table(['Veículo', 'Placa', 'Com quem', 'Onde está', 'Situação', '>Saiu em'], $linhas, 'Nenhum veículo cadastrado na frota.');
    }
}
