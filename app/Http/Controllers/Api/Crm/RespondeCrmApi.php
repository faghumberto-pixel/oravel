<?php

namespace App\Http\Controllers\Api\Crm;

use Illuminate\Http\JsonResponse;

/** Formato único de resposta da API do CRM: {data: …} ou {erro: {codigo, mensagem, …}}. */
trait RespondeCrmApi
{
    protected function ok(mixed $data, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $data] + $extra, $status);
    }

    protected function erro(string $codigo, string $mensagem, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['erro' => ['codigo' => $codigo, 'mensagem' => $mensagem] + $extra], $status);
    }
}
