<?php

namespace App\Services;

use App\Models\PropostaComercial;

class PropostaComercialAiEvaluator
{
    public function __construct(private readonly AnthropicApiClient $client) {}

    /**
     * @return array{risco_coerencia: array{nota: int, comentario: string}, qualidade_clareza: array{nota: int, comentario: string}, probabilidade_fechamento: array{nota: int, comentario: string}}
     */
    public function evaluate(PropostaComercial $proposta): array
    {
        $systemPrompt = <<<'PROMPT'
        Você avalia propostas comerciais de locação de equipamentos. Responda
        SOMENTE em JSON com esta estrutura exata:
        {"risco_coerencia": {"nota": 1-5, "comentario": "..."},
         "qualidade_clareza": {"nota": 1-5, "comentario": "..."},
         "probabilidade_fechamento": {"nota": 1-5, "comentario": "..."}}
        PROMPT;

        $userContent = "Cliente: {$proposta->client?->name}\n"
            ."Valor total: R$ {$proposta->total_value}\n"
            ."Validade: {$proposta->valid_until}\n"
            ."Termos: {$proposta->terms}\n"
            ."Itens:\n".$proposta->items->map(fn ($i) => "- {$i->description} (qtd {$i->quantity}, R$ {$i->unit_price})")->implode("\n");

        $response = $this->client->send($systemPrompt, $userContent, 1024);

        if (! $response['ok']) {
            throw new \RuntimeException($response['error'] ?? 'Falha ao avaliar a proposta com IA.');
        }

        $parsed = $this->client->parseJson($response['text']);

        if (! $parsed) {
            throw new \RuntimeException('A IA não retornou um parecer em formato reconhecível.');
        }

        $proposta->update(['ai_evaluation' => $parsed, 'ai_evaluated_at' => now()]);

        return $parsed;
    }
}
