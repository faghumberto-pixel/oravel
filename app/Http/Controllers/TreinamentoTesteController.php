<?php

namespace App\Http\Controllers;

use App\Mail\TreinamentoResultadoMail;
use App\Models\TreinamentoResultado;
use App\Support\TreinamentoTeste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Teste público (sem login) do treinamento "Cadastros, Propostas e Contas a
 * Receber". Correção sempre no servidor; o gabarito só aparece depois do envio.
 */
class TreinamentoTesteController extends Controller
{
    private const SESSAO_ORDEM = 'treinamento_teste.ordem';

    private const SESSAO_RESULTADO = 'treinamento_teste.resultado_id';

    public function show(): View
    {
        $perguntas = TreinamentoTeste::perguntas();
        $ordem = TreinamentoTeste::sortearOrdem($perguntas);

        session()->put(self::SESSAO_ORDEM, $ordem);

        // Só o enunciado e as alternativas na ordem sorteada: nada de gabarito no HTML.
        $paraTela = array_map(fn (array $p) => [
            'n' => $p['n'],
            'q' => $p['q'],
            'alternativas' => array_map(fn (int $original) => $p['options'][$original], $ordem[$p['n']]),
        ], $perguntas);

        return view('treinamento.teste', [
            'titulo' => TreinamentoTeste::TITULO,
            'perguntas' => $paraTela,
            'temPdf' => TreinamentoTeste::temPdf(),
            'notaMinima' => (float) config('oravel.academy.passing_grade', 7.0),
        ]);
    }

    public function enviar(Request $request): RedirectResponse
    {
        // Honeypot: robô preenche o campo escondido. Responde como se nada tivesse acontecido.
        if (filled($request->input('website'))) {
            return redirect()->route('treinamento.teste');
        }

        $chave = 'treinamento-teste:'.$request->ip();
        $limite = (int) config('oravel.treinamento.limite_por_hora', 5);

        if (RateLimiter::tooManyAttempts($chave, $limite)) {
            $minutos = (int) ceil(RateLimiter::availableIn($chave) / 60);

            return back()->withInput($request->except('r'))->withErrors([
                'limite' => "Muitos envios deste endereço. Tente de novo em {$minutos} minuto(s).",
            ]);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'funcao' => ['required', 'string', 'max:120'],
            'r' => ['nullable', 'array'],
            'r.*' => ['nullable', 'integer', 'between:0,3'],
        ], [], ['nome' => 'nome completo', 'funcao' => 'função']);

        $ordem = session(self::SESSAO_ORDEM);

        if (! is_array($ordem)) {
            return back()->withInput($request->only('nome', 'funcao'))->withErrors([
                'sessao' => 'A página expirou. Atualize e responda de novo.',
            ]);
        }

        $perguntas = TreinamentoTeste::perguntas();
        $correcao = TreinamentoTeste::corrigir($perguntas, $ordem, $dados['r'] ?? []);

        // Grava antes de qualquer e-mail: falha de SMTP não pode perder o resultado.
        $resultado = TreinamentoResultado::create([
            'treinamento' => TreinamentoTeste::SLUG,
            'nome' => trim($dados['nome']),
            'funcao' => trim($dados['funcao']),
            'acertos' => $correcao['acertos'],
            'total' => $correcao['total'],
            'nota' => $correcao['nota'],
            'aprovado' => $correcao['aprovado'],
            'respostas' => $correcao['respostas'],
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        RateLimiter::hit($chave, 3600);

        $this->enviarEmail($resultado, $perguntas);

        session()->forget(self::SESSAO_ORDEM);
        session()->put(self::SESSAO_RESULTADO, $resultado->id);

        return redirect()->route('treinamento.resultado');
    }

    public function resultado(): View|RedirectResponse
    {
        $resultado = TreinamentoResultado::find(session(self::SESSAO_RESULTADO));

        if (! $resultado) {
            return redirect()->route('treinamento.teste');
        }

        $perguntas = TreinamentoTeste::perguntas();
        $revisao = [];

        foreach ($perguntas as $p) {
            $escolhida = $resultado->respostas[$p['n']] ?? null;
            $revisao[] = [
                'n' => $p['n'],
                'q' => $p['q'],
                'escolhida' => $escolhida !== null ? ($p['options'][$escolhida] ?? null) : null,
                'certa' => $escolhida === $p['correct'],
                'resposta_certa' => $p['options'][$p['correct']],
                'why' => $p['why'],
            ];
        }

        return view('treinamento.resultado', [
            'titulo' => TreinamentoTeste::TITULO,
            'resultado' => $resultado,
            'revisao' => $revisao,
            'notaMinima' => (float) config('oravel.academy.passing_grade', 7.0),
        ]);
    }

    public function pdf(): BinaryFileResponse
    {
        abort_unless(TreinamentoTeste::temPdf(), 404);

        return response()->file(TreinamentoTeste::caminhoPdf(), ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  array<int, array{n: int, q: string, options: array<int, string>, correct: int, why: string}>  $perguntas
     */
    private function enviarEmail(TreinamentoResultado $resultado, array $perguntas): void
    {
        try {
            $linhas = array_map(function (array $p) use ($resultado) {
                $escolhida = $resultado->respostas[$p['n']] ?? null;

                return [
                    'n' => $p['n'],
                    'pergunta' => $p['q'],
                    'escolhida' => $escolhida !== null ? ($p['options'][$escolhida] ?? null) : null,
                    'certa' => $escolhida === $p['correct'],
                ];
            }, $perguntas);

            Mail::to(config('oravel.treinamento.email'))
                ->send(new TreinamentoResultadoMail($resultado, $linhas, TreinamentoTeste::TITULO));
        } catch (\Throwable $e) {
            Log::error('Treinamento: falha ao enviar o e-mail do resultado '.$resultado->id.': '.$e->getMessage());
        }
    }
}
