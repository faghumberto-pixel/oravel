<?php

namespace App\Support;

/** Cálculos de rota sem API paga: distância em linha reta, ordem por vizinho mais próximo e link do mapa. */
class CrmRota
{
    /** Fator para transformar linha reta em distância aproximada por estrada. */
    public const FATOR_ESTRADA = 1.3;

    /** Distância em linha reta, em metros (fórmula de haversine). */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Ordena pontos pelo vizinho mais próximo a partir de uma origem. Pontos sem coordenada vão ao fim,
     * na ordem original.
     *
     * @param  array<int|string, array{lat: ?float, lng: ?float}>  $pontos  chave => coordenadas
     * @param  array{lat: float, lng: float}|null  $origem  se nulo, parte do primeiro ponto com coordenada
     * @return array<int, int|string> chaves na ordem sugerida
     */
    public static function ordenar(array $pontos, ?array $origem = null): array
    {
        $comCoord = array_filter($pontos, fn ($p) => $p['lat'] !== null && $p['lng'] !== null);
        $semCoord = array_keys(array_diff_key($pontos, $comCoord));

        if (! $comCoord) {
            return $semCoord;
        }

        $atual = $origem ?? ['lat' => reset($comCoord)['lat'], 'lng' => reset($comCoord)['lng']];
        $ordem = [];

        while ($comCoord) {
            $melhor = null;
            $menor = INF;

            foreach ($comCoord as $chave => $p) {
                $d = self::haversine($atual['lat'], $atual['lng'], $p['lat'], $p['lng']);
                if ($d < $menor) {
                    $menor = $d;
                    $melhor = $chave;
                }
            }

            $ordem[] = $melhor;
            $atual = $comCoord[$melhor];
            unset($comCoord[$melhor]);
        }

        return array_merge($ordem, $semCoord);
    }

    /**
     * Quilômetros estimados por estrada passando pelos pontos na ordem dada (linha reta × fator).
     *
     * @param  array<int, array{lat: float, lng: float}>  $sequencia
     */
    public static function kmEstimados(array $sequencia): float
    {
        $total = 0.0;

        for ($i = 1; $i < count($sequencia); $i++) {
            $total += self::haversine($sequencia[$i - 1]['lat'], $sequencia[$i - 1]['lng'], $sequencia[$i]['lat'], $sequencia[$i]['lng']);
        }

        return round($total * self::FATOR_ESTRADA / 1000, 1);
    }

    /**
     * Link do Google Maps com a rota (origem, até 9 paradas intermediárias e destino). Sem API key.
     *
     * @param  array<int, array{lat: float, lng: float}>  $sequencia
     */
    public static function linkGoogleMaps(array $sequencia): ?string
    {
        if (count($sequencia) < 1) {
            return null;
        }

        $f = fn ($p) => $p['lat'].','.$p['lng'];

        if (count($sequencia) === 1) {
            return 'https://www.google.com/maps/dir/?api=1&destination='.$f($sequencia[0]).'&travelmode=driving';
        }

        $origem = $sequencia[0];
        $destino = $sequencia[count($sequencia) - 1];
        $meio = array_slice($sequencia, 1, -1);
        $meio = array_slice($meio, 0, 9);

        return 'https://www.google.com/maps/dir/?api=1&origin='.$f($origem).'&destination='.$f($destino)
            .($meio ? '&waypoints='.implode('|', array_map($f, $meio)) : '').'&travelmode=driving';
    }
}
