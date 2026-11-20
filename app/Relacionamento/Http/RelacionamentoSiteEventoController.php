<?php

namespace App\Relacionamento\Http;

use App\Http\Controllers\Controller;
use App\Models\WebIpIgnorado;
use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Services\RegistraInteracao;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/relacionamento/site-evento -- sinal ANONIMO do navegador do oravel.org (ferramenta concluida,
 * download), via sendBeacon (text/plain, sem preflight). Mesmo contrato do /api/site-track: sempre 204, sem
 * revelar nada; origem nao autorizada, payload invalido ou erro sao ignorados em silencio.
 *
 * Nao aceita e-mail/telefone de proposito: o navegador nao guarda segredo, entao qualquer um poderia forjar
 * eventos "em nome" de uma pessoa e inflar o score dela. Evento identificado so' entra pelo endpoint com token
 * (/api/relacionamento/eventos, servidor a servidor). Este aqui so' grava o token do navegador; o vinculo com a
 * pessoa nasce quando ela se identifica. Serve para estatistica de uso: por ser forjavel (o Origin e' facil de
 * simular fora de um navegador), trate como indicativo.
 */
class RelacionamentoSiteEventoController extends Controller
{
    private const EVENTOS = ['tool_complete', 'download'];

    public function store(Request $request, RegistraInteracao $registra): Response
    {
        $resposta = response()->noContent();

        if (! $this->origemPermitida($request) || WebIpIgnorado::ignora($request->ip())) {
            return $resposta; // origem nao autorizada ou IP da equipe/empresa (ver WebIpIgnorado)
        }

        try {
            $raw = $request->getContent();
            $d = strlen($raw) <= 2048 ? json_decode($raw, true) : null;
            if (! is_array($d) || ! in_array($d['evento'] ?? null, self::EVENTOS, true)) {
                return $resposta;
            }

            $token = $this->token($d['visitor'] ?? null);
            if ($token === null) {
                return $resposta;
            }

            $dados = $d['evento'] === 'tool_complete' ? $this->ferramenta($d) : $this->download($d);
            if ($dados === null) {
                return $resposta;
            }

            $registra->registrar($dados + [
                'visitor_token' => $token,
                'origem' => 'oravel-org',
            ]);
        } catch (\Throwable $e) {
            Log::warning('relacionamento.site-evento: '.$e->getMessage());
        }

        return $resposta;
    }

    private function ferramenta(array $d): ?array
    {
        $slug = $this->slug($d['ferramenta'] ?? null);
        if ($slug === null) {
            return null;
        }

        return [
            'tipo' => RelInteracao::TIPO_FERRAMENTA,
            'conteudo_slug' => 'ferramentas/'.$slug,
            'titulo' => $this->texto($d['titulo'] ?? null, 200) ?? $slug,
            'duracao_segundos' => isset($d['segundos']) ? max(0, min(14400, (int) $d['segundos'])) : null,
            'dados' => array_filter(['uf' => $this->uf($d['uf'] ?? null)]),
        ];
    }

    private function download(array $d): ?array
    {
        $path = is_string($d['arquivo'] ?? null) ? (string) parse_url($d['arquivo'], PHP_URL_PATH) : '';
        if (! preg_match('#^/(downloads|assets/materiais)/([A-Za-z0-9._\-]{1,120})$#', $path, $m)) {
            return null;
        }

        return [
            'tipo' => RelInteracao::TIPO_PDF,
            'conteudo_slug' => strtolower($m[1].'/'.pathinfo($m[2], PATHINFO_FILENAME)),
            'titulo' => $m[2],
        ];
    }

    private function origemPermitida(Request $request): bool
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $host = $origin ? parse_url($origin, PHP_URL_HOST) : null;

        return $host && in_array(strtolower($host), (array) config('services.relacionamento.site_hosts', []), true);
    }

    private function token(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[A-Za-z0-9\-]{8,64}$/', $v) ? $v : null;
    }

    private function slug(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[a-z0-9\-]{1,100}$/', $v) ? $v : null;
    }

    private function uf(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[A-Za-z]{2}$/', $v) ? strtoupper($v) : null;
    }

    private function texto(mixed $v, int $max): ?string
    {
        $t = is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : '';

        return $t === '' ? null : mb_substr($t, 0, $max);
    }
}
