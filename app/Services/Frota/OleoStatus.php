<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaPlanoOleo;
use App\Models\FrotaTrocaOleo;

/** Situação do óleo de um veículo, calculada ao vivo (sem tabela de alertas). */
class OleoStatus
{
    public const SEM_PLANO = 'sem_plano';

    public const SEM_REGISTRO = 'sem_registro';

    public const EM_DIA = 'em_dia';

    public const PROXIMA = 'proxima';

    public const VENCIDA = 'vencida';

    /** @return array{situacao: string, mensagem: string, km_restantes: ?int, dias_restantes: ?int, proxima_km: ?int, proxima_data: ?string} */
    public static function para(Asset $ativo): array
    {
        $base = ['situacao' => self::SEM_PLANO, 'mensagem' => 'Sem plano de óleo cadastrado', 'km_restantes' => null, 'dias_restantes' => null, 'proxima_km' => null, 'proxima_data' => null];

        $plano = FrotaPlanoOleo::where('ativo_id', $ativo->id)->where('ativo', true)->first();
        if (! $plano) {
            return $base;
        }

        $ultima = FrotaTrocaOleo::where('ativo_id', $ativo->id)->where('tipo', FrotaTrocaOleo::TROCA)->orderByDesc('realizado_em')->first();
        if (! $ultima) {
            return array_merge($base, ['situacao' => self::SEM_REGISTRO, 'mensagem' => 'Plano cadastrado, mas nenhuma troca registrada ainda']);
        }

        $km = $ultima->proxima_troca_odometro !== null ? $ultima->proxima_troca_odometro - (int) floor((float) $ativo->odometro_atual) : null;
        $dias = $ultima->proxima_troca_data ? (int) now()->startOfDay()->diffInDays($ultima->proxima_troca_data->copy()->startOfDay(), false) : null;

        $situacao = match (true) {
            ($km !== null && $km <= 0) || ($dias !== null && $dias <= 0) => self::VENCIDA,
            ($km !== null && $km <= FrotaTrocaOleo::AVISO_KM) || ($dias !== null && $dias <= FrotaTrocaOleo::AVISO_DIAS) => self::PROXIMA,
            default => self::EM_DIA,
        };

        $partes = array_filter([
            $km !== null ? ($km <= 0 ? 'passou '.number_format(abs($km), 0, ',', '.').' km' : 'faltam '.number_format($km, 0, ',', '.').' km') : null,
            $dias !== null ? ($dias <= 0 ? 'venceu há '.abs($dias).' dia(s)' : "faltam {$dias} dia(s)") : null,
        ]);

        return [
            'situacao' => $situacao,
            'mensagem' => ['vencida' => 'Troca de óleo VENCIDA', 'proxima' => 'Troca de óleo próxima', 'em_dia' => 'Óleo em dia'][$situacao].' ('.implode(' · ', $partes).')',
            'km_restantes' => $km,
            'dias_restantes' => $dias,
            'proxima_km' => $ultima->proxima_troca_odometro,
            'proxima_data' => $ultima->proxima_troca_data?->toDateString(),
        ];
    }

    /**
     * Consumo anormal: litros repostos desde a última troca acima do limite (1 L por 1.000 km rodados desde ela).
     *
     * @return array{litros: float, limite: float, km: int}|null
     */
    public static function consumoAnormal(Asset $ativo): ?array
    {
        $ultima = FrotaTrocaOleo::where('ativo_id', $ativo->id)->where('tipo', FrotaTrocaOleo::TROCA)->orderByDesc('realizado_em')->first();
        if (! $ultima) {
            return null;
        }

        $litros = (float) FrotaTrocaOleo::where('ativo_id', $ativo->id)->where('tipo', FrotaTrocaOleo::REPOSICAO)->where('realizado_em', '>=', $ultima->realizado_em)->sum('litros');
        $km = max(0, (int) floor((float) $ativo->odometro_atual) - $ultima->odometro);
        $limite = round($km / 1000 * FrotaTrocaOleo::LIMITE_REPOSICAO_LITROS_POR_1000_KM, 1);

        return $litros > 0 && $litros > $limite ? ['litros' => round($litros, 1), 'limite' => $limite, 'km' => $km] : null;
    }
}
