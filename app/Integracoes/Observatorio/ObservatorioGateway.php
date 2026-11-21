<?php

namespace App\Integracoes\Observatorio;

/** Porta de saida da Central para o Observatorio. Unica forma de falar com ele (desacoplamento). */
interface ObservatorioGateway
{
    /** Definicao das pesquisas ativas (cache curto). Lanca ObservatorioIndisponivel se nao der. @return array<int, array> */
    public function pesquisasAtivas(): array;

    /** Envia um envelope de evento. Nunca lanca por erro de rede: devolve ResultadoEnvio com status null. */
    public function enviar(array $envelope): ResultadoEnvio;
}
