<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * IP (ou faixa CIDR) cujas visitas os sites da Oravel NAO contam: a equipe e a rede da empresa. Vale para o
 * coletor de visitas (/api/site-track, oravel.com.br e oravel.org) e para o sinal de ferramentas/downloads.
 * Tabela global (sem tenant), como as demais de analytics do site.
 */
class WebIpIgnorado extends Model
{
    use HasUuids;

    protected $table = 'web_ips_ignorados';

    protected $guarded = [];

    private const CACHE = 'web-ips-ignorados';

    protected static function booted(): void
    {
        // A lista fica em cache por 1 min (o coletor roda a cada pagina vista); mudou, vale na hora.
        static::saved(fn () => Cache::forget(self::CACHE));
        static::deleted(fn () => Cache::forget(self::CACHE));
    }

    /** True se o IP esta na lista (IP exato ou dentro de uma faixa). Nunca lanca: sem a tabela ou sem IP, nao ignora. */
    public static function ignora(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        try {
            $lista = Cache::remember(self::CACHE, 60, fn () => self::query()->pluck('ip')->all());

            return $lista !== [] && IpUtils::checkIp($ip, $lista);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Regra de validacao: IP valido ou faixa CIDR razoavel (nada que ignore a internet inteira). */
    public static function entradaValida(string $valor): bool
    {
        $valor = trim($valor);

        if (! str_contains($valor, '/')) {
            return filter_var($valor, FILTER_VALIDATE_IP) !== false;
        }

        [$ip, $bits] = explode('/', $valor, 2);
        if (! ctype_digit($bits) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $v6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        return $v6 ? ((int) $bits >= 32 && (int) $bits <= 128) : ((int) $bits >= 16 && (int) $bits <= 32);
    }

    /** Apaga as visitas JA registradas desse IP/faixa (e seus pageviews/cliques, por cascata). Devolve quantas. */
    public static function apagarVisitasDe(string $entrada): int
    {
        $enderecos = WebVisit::query()->whereNotNull('ip_address')->distinct()->pluck('ip_address')
            ->filter(fn ($ip) => IpUtils::checkIp((string) $ip, $entrada))->values()->all();

        return $enderecos === [] ? 0 : WebVisit::whereIn('ip_address', $enderecos)->delete();
    }
}
