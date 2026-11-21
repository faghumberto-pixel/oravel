<?php

namespace App\Integracoes\Observatorio;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Cliente HTTP do contrato v1: token de servico + HMAC-SHA256(timestamp.corpo) (ver contrato/v1/README.md). */
class HttpObservatorioGateway implements ObservatorioGateway
{
    private const CACHE = 'observatorio.pesquisas_ativas';

    public static function esquecerCache(): void
    {
        Cache::forget(self::CACHE);
    }

    public function configurado(): bool
    {
        return filled(config('observatorio.url')) && filled(config('observatorio.token')) && filled(config('observatorio.segredo'));
    }

    public function pesquisasAtivas(): array
    {
        if (! $this->configurado()) {
            throw new ObservatorioIndisponivel('Integração com o Observatório não configurada.');
        }

        return Cache::remember(self::CACHE, config('observatorio.cache_pesquisas_segundos'), function () {
            try {
                $r = $this->requisicao('')->get(rtrim(config('observatorio.url'), '/').'/pesquisas/ativas');
            } catch (ConnectionException $e) {
                throw new ObservatorioIndisponivel('Observatório fora do ar.', 0, $e);
            }
            if (! $r->ok()) {
                throw new ObservatorioIndisponivel('Observatório respondeu '.$r->status().' ao listar pesquisas.');
            }

            return $r->json('pesquisas', []);
        });
    }

    public function enviar(array $envelope): ResultadoEnvio
    {
        if (! $this->configurado()) {
            return new ResultadoEnvio(null, null, 'Integração com o Observatório não configurada.');
        }
        $corpo = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $r = $this->requisicao($corpo)->withBody($corpo, 'application/json')->post(rtrim(config('observatorio.url'), '/').'/eventos');
        } catch (ConnectionException $e) {
            return new ResultadoEnvio(null, null, 'Sem conexão com o Observatório.');
        }

        return new ResultadoEnvio($r->status(), $r->json());
    }

    private function requisicao(string $corpo)
    {
        $ts = (string) time();

        return Http::timeout(config('observatorio.timeout'))->acceptJson()->withToken(config('observatorio.token'))->withHeaders([
            'X-Oravel-Timestamp' => $ts,
            'X-Oravel-Signature' => hash_hmac('sha256', $ts.'.'.$corpo, config('observatorio.segredo')),
        ]);
    }
}
