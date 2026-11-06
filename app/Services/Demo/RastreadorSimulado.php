<?php

namespace App\Services\Demo;

use Illuminate\Support\Carbon;

/**
 * Posicao de rastreador SIMULADA, so' para a empresa de demonstracao (tenant is_demo): na demo nao existe aparelho
 * Traccar de verdade. Deterministica no tempo: os equipamentos "em deslocamento" andam de um lado para o outro perto
 * da obra (vai e volta de ~3 km a cada 50 minutos) e os parados ficam na obra. Nunca e' usada em tenant real.
 */
class RastreadorSimulado
{
    /** Equipamentos da Locadora Demo com rastreador (patrimonio) e se estao em deslocamento. */
    public const EQUIPAMENTOS = ['PT-05' => true, 'PA-01' => true, 'G-03' => false, 'C-01' => false, 'PT-02' => false];

    private const AMPLITUDE = 0.027; // graus (~3 km)

    private const PERIODO_MIN = 50;

    public static function tem(?string $patrimonio): bool
    {
        return $patrimonio !== null && array_key_exists($patrimonio, self::EQUIPAMENTOS);
    }

    /** @return array{lat: float, lng: float, velocidade: float, atualizado_em: string, trilha: array<int, array{0: float, 1: float}>} */
    public function posicao(string $patrimonio, float $latBase, float $lngBase, ?Carbon $quando = null): array
    {
        $quando ??= Carbon::now();
        $movendo = self::EQUIPAMENTOS[$patrimonio] ?? false;
        [$dLat, $dLng] = $this->direcao($patrimonio);

        $ponto = fn (Carbon $t): array => $movendo
            ? [
                round($latBase + $dLat * self::AMPLITUDE * sin(2 * M_PI * ($t->timestamp / 60) / self::PERIODO_MIN), 6),
                round($lngBase + $dLng * self::AMPLITUDE * sin(2 * M_PI * ($t->timestamp / 60) / self::PERIODO_MIN), 6),
            ]
            : [round($latBase + $dLat * 0.0004, 6), round($lngBase + $dLng * 0.0004, 6)];

        [$lat, $lng] = $ponto($quando);
        $velocidade = $movendo ? round(abs(cos(2 * M_PI * ($quando->timestamp / 60) / self::PERIODO_MIN)) * 34 + 6, 1) : 0.0;

        $trilha = [];
        if ($movendo) {
            for ($min = 360; $min >= 0; $min -= 20) {
                $trilha[] = $ponto($quando->copy()->subMinutes($min));
            }
        }

        return ['lat' => $lat, 'lng' => $lng, 'velocidade' => $velocidade, 'atualizado_em' => $quando->format('d/m/Y H:i'), 'trilha' => $trilha];
    }

    /** Direcao (versor aproximado) do vai e volta, fixa por equipamento. @return array{0: float, 1: float} */
    private function direcao(string $patrimonio): array
    {
        $ang = (crc32($patrimonio) % 360) * M_PI / 180;

        return [round(sin($ang), 3), round(cos($ang), 3)];
    }
}
