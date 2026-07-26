<?php

namespace App\Services;

/**
 * Otimizacao de sequencia de paradas por distancia aproximada (Haversine,
 * linha reta) -- sem API de rotas paga, nao reflete transito/malha viaria
 * real. Heuristica "vizinho mais proximo" (nearest neighbor): a cada
 * passo, vai pra parada nao visitada mais perto da posicao atual. Nao e'
 * o otimo matematico (TSP e' NP-dificil), mas e' rapido e da' resultado
 * razoavel pro numero pequeno de paradas por dia que uma frota de munck
 * costuma ter.
 */
class RouteOptimizationService
{
    private const EARTH_RADIUS_KM = 6371;

    /**
     * @param  array{lat: float, lng: float}  $origin
     * @param  array<int, array{id: mixed, lat: float, lng: float, label: string}>  $stops
     * @return array{order: array<int, array{id: mixed, lat: float, lng: float, label: string}>, total_km: float}
     */
    public function optimize(array $origin, array $stops): array
    {
        $remaining = $stops;
        $current = $origin;
        $ordered = [];
        $totalKm = 0.0;

        while (! empty($remaining)) {
            $nearestIndex = null;
            $nearestDistance = null;

            foreach ($remaining as $index => $stop) {
                $distance = $this->haversineKm($current['lat'], $current['lng'], $stop['lat'], $stop['lng']);

                if ($nearestDistance === null || $distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            $nearest = $remaining[$nearestIndex];
            $ordered[] = $nearest;
            $totalKm += $nearestDistance;
            $current = ['lat' => $nearest['lat'], 'lng' => $nearest['lng']];
            unset($remaining[$nearestIndex]);
        }

        return ['order' => $ordered, 'total_km' => round($totalKm, 1)];
    }

    /**
     * Distancia total de uma sequencia JA' dada (pra comparar com a
     * otimizada -- "cenario atual" vs "cenario otimizado").
     *
     * @param  array{lat: float, lng: float}  $origin
     * @param  array<int, array{lat: float, lng: float}>  $orderedStops
     */
    public function routeDistanceKm(array $origin, array $orderedStops): float
    {
        $current = $origin;
        $total = 0.0;

        foreach ($orderedStops as $stop) {
            $total += $this->haversineKm($current['lat'], $current['lng'], $stop['lat'], $stop['lng']);
            $current = ['lat' => $stop['lat'], 'lng' => $stop['lng']];
        }

        return round($total, 1);
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
