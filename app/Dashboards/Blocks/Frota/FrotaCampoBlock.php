<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaSinistro;
use App\Models\FrotaVinculoMotorista;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;

/** Base dos blocos de "Logística da Frota": onde estão os veículos, com quem estão e como estão sendo usados (saídas e retornos). */
abstract class FrotaCampoBlock extends FrotaBlock
{
    use SeriesDeTempo;

    /** Chave de contrato (config/menu_modules.php) que libera o painel; desligada por padrao, a Central liga por cliente. */
    public const CHAVE = 'menu_visao_geral_logistica_frota';

    public static function visibleTo(?User $user): bool
    {
        if (! parent::visibleTo($user)) {
            return false;
        }

        return Tenancy::current()?->hasFeature(self::CHAVE) ?? false;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_saidas_veiculo';
    }

    public static function sourceModel(): string
    {
        return FrotaSaidaVeiculo::class;
    }

    /** @return Collection<int, Asset> */
    protected function veiculos()
    {
        return Asset::query()->where('is_vehicle', true)->orderBy('name')->get(['id', 'name', 'placa', 'internal_unit_id']);
    }

    /** Unidade onde ficam os veiculos que nao estao na rua: a do cadastro do veiculo ou, sem ela, a principal da empresa (a mais antiga). */
    protected function unidadeDoPatio($veiculo, $unidades)
    {
        return $unidades->get($veiculo->internal_unit_id) ?? $unidades->first();
    }

    /** Saidas ainda sem retorno, da mais antiga para a mais nova. */
    protected function naRua()
    {
        return FrotaSaidaVeiculo::query()->whereNull('retorno_em')->orderBy('saida_em')->get();
    }

    /** @return array<string, string> ativo_id => nome do titular vigente */
    protected function titulares(): array
    {
        $vinculos = FrotaVinculoMotorista::query()->whereNull('fim')->get(['ativo_id', 'motorista_id']);
        $nomes = FleetDriver::query()->whereIn('id', $vinculos->pluck('motorista_id'))->pluck('name', 'id');

        return $vinculos->mapWithKeys(fn ($v) => [(string) $v->ativo_id => (string) ($nomes[$v->motorista_id] ?? '')])->filter()->all();
    }

    /** @return array<string, true> ativo_id dos veiculos parados por sinistro em aberto */
    protected function paradosPorSinistro(): array
    {
        return FrotaSinistro::query()->whereNotIn('situacao', [FrotaSinistro::ENCERRADO, FrotaSinistro::CANCELADO])->where('veiculo_parado', true)
            ->pluck('ativo_id')->mapWithKeys(fn ($id) => [(string) $id => true])->all();
    }

    protected function finalidades(): array
    {
        return FrotaSaidaVeiculo::finalidadeLabels();
    }

    /** Km rodado numa saida concluida (0 quando o odometro nao fecha). */
    protected function kmDaSaida($s): int
    {
        return $s->odometro_retorno !== null && $s->odometro_retorno >= $s->odometro_saida ? (int) ($s->odometro_retorno - $s->odometro_saida) : 0;
    }

    protected function horasFora($saida): int
    {
        return (int) $saida->saida_em->diffInHours(now());
    }
}
