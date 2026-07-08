<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transcricao de audio via OpenAI Whisper. Sem OPENAI_API_KEY configurada,
 * transcribe() retorna null silenciosamente -- o audio do chat continua
 * funcionando normalmente, so fica sem o texto.
 */
class AudioTranscriptionService
{
    public function transcribe(string $filePath, string $filename = 'audio.webm'): ?string
    {
        $apiKey = config('services.openai.key');

        if (blank($apiKey) || ! is_file($filePath)) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->attach('file', file_get_contents($filePath), $filename)
                ->asMultipart()
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    ['name' => 'model', 'contents' => 'whisper-1'],
                    ['name' => 'language', 'contents' => 'pt'],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao transcrever audio do chat (Whisper)', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning('OpenAI Whisper retornou erro', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return trim((string) $response->json('text')) ?: null;
    }
}
