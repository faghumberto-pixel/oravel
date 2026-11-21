<?php

namespace App\Integracoes\Observatorio;

/** Resultado de um envio. status null = nao chegou ao Observatorio (rede/timeout). */
final class ResultadoEnvio
{
    public function __construct(public readonly ?int $status, public readonly ?array $corpo = null, public readonly ?string $erro = null) {}

    public function entregue(): bool
    {
        return in_array($this->status, [200, 202], true);
    }

    /** Falhas que valem nova tentativa: rede, auth/config (401/503), limite (429) e erros 5xx. */
    public function transitorio(): bool
    {
        return $this->status === null || in_array($this->status, [401, 403, 429], true) || $this->status >= 500;
    }
}
