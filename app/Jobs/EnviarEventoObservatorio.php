<?php

namespace App\Jobs;

use App\Integracoes\Observatorio\HttpObservatorioGateway;
use App\Integracoes\Observatorio\ObservatorioGateway;
use App\Integracoes\Observatorio\ResultadoEnvio;
use App\Models\ObservatorioEnvio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Entrega um evento do outbox ao Observatorio.
 *  - 200/202: enviado, e o payload (respostas) e' apagado da Central.
 *  - 409: a pesquisa mudou; nao reenviar sozinho (as respostas seguem a versao antiga).
 *  - 422: erro de contrato; nao reenviar.
 *  - rede/401/429/5xx: nova tentativa com backoff exponencial ate config('observatorio.max_tentativas').
 */
class EnviarEventoObservatorio implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $envioId) {}

    public function handle(ObservatorioGateway $gateway): void
    {
        $envio = ObservatorioEnvio::find($this->envioId);
        if (! $envio || $envio->status !== 'pendente' || ! $envio->payload) {
            return;
        }

        // Em producao a fila e' "sync": este codigo roda DENTRO da requisicao de quem registrou a entrevista, depois de
        // os dados ja terem sido gravados. Qualquer falha inesperada vira nova tentativa, nunca erro na tela do usuario.
        try {
            $r = $gateway->enviar($envio->payload);
        } catch (\Throwable $e) {
            $r = new ResultadoEnvio(null, null, 'Falha inesperada: '.mb_substr($e->getMessage(), 0, 200));
        }

        if ($r->entregue()) {
            $envio->update(['status' => 'enviado', 'enviado_em' => now(), 'payload' => null, 'proxima_tentativa_em' => null, 'ultimo_erro' => null, 'tentativas' => $envio->tentativas + 1]);

            return;
        }

        $detalhe = $r->erro ?? mb_substr((string) json_encode($r->corpo, JSON_UNESCAPED_UNICODE), 0, 1000);

        if ($r->status === 409) {
            HttpObservatorioGateway::esquecerCache();
            $envio->update(['status' => 'versao_desatualizada', 'proxima_tentativa_em' => null, 'ultimo_erro' => $detalhe, 'tentativas' => $envio->tentativas + 1]);

            return;
        }
        if (! $r->transitorio()) {
            $envio->update(['status' => 'rejeitado', 'proxima_tentativa_em' => null, 'ultimo_erro' => 'HTTP '.$r->status.' '.$detalhe, 'tentativas' => $envio->tentativas + 1]);

            return;
        }

        $tentativas = $envio->tentativas + 1;
        $esgotou = $tentativas >= (int) config('observatorio.max_tentativas');
        $envio->update([
            'status' => $esgotou ? 'falhou' : 'pendente', 'tentativas' => $tentativas,
            'ultimo_erro' => ($r->status ? 'HTTP '.$r->status.' ' : '').$detalhe,
            'proxima_tentativa_em' => $esgotou ? null : now()->addMinutes(min(2 ** $tentativas, 360)),
        ]);
    }
}
