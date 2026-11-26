<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\ConfirmacaoDeEmail;
use App\Domain\Intelligence\Trial\Services\RecuperacaoDeAcesso;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Domain\Intelligence\Trial\Services\Turnstile;
use App\Filament\Resources\IntelligenceImportResource;
use App\Http\Requests\IntelligenceTrial\CadastroTrialRequest;
use App\Http\Requests\IntelligenceTrial\NovoLinkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Cadastro rápido sem senha do teste grátis do Intelligence, entrada por link pessoal e novo link por WhatsApp. */
class IntelligenceTrialController extends Controller
{
    public function create(): View
    {
        return view('intelligence-trial.cadastro');
    }

    /** Recebe o cadastro (do site ou da tela do app). Não cria conta: manda o link de confirmação ao e-mail e mostra a tela "verifique seu e-mail". */
    public function store(CadastroTrialRequest $request, ConfirmacaoDeEmail $confirmacao, Turnstile $turnstile): RedirectResponse
    {
        if (! $this->origemPermitida($request)) {
            return redirect()->route('intelligence.trial.create')->withErrors(['nome' => 'Não foi possível enviar por aqui. Preencha o cadastro nesta página.']);
        }
        if (! $turnstile->verificar($request->input('cf-turnstile-response'), $request->ip())) {
            return redirect()->route('intelligence.trial.create')->withInput($request->except('cf-turnstile-response'))
                ->withErrors(['nome' => 'Não conseguimos confirmar que você não é um robô. Tente de novo.']);
        }

        $confirmacao->solicitar([
            'nome' => trim($request->string('nome')),
            'empresa' => trim($request->string('empresa')),
            'whatsapp' => $request->whatsappE164(),
            'email' => trim($request->string('email')),
        ], $request->ip(), $request->userAgent());

        // Mesma resposta exista conta ou não: a tela nunca revela se o e-mail ou o WhatsApp já têm teste.
        return redirect()->route('intelligence.trial.verifique')->with('email_enviado_para', Str::lower(trim($request->string('email'))));
    }

    public function verifique(): View
    {
        return view('intelligence-trial.verifique-email');
    }

    /** O link do e-mail só mostra a tela com o botão: quem confirma é o POST (leitores de e-mail e antivírus abrem links sozinhos e gastariam o link). */
    public function confirmarTela(string $token, ConfirmacaoDeEmail $confirmacao): View|RedirectResponse
    {
        if (! $confirmacao->valido($token)) {
            return redirect()->route('intelligence.trial.create')->withErrors(['nome' => 'Este link de confirmação não vale mais (venceu ou já foi usado). Faça o cadastro de novo.']);
        }

        return view('intelligence-trial.confirmar', ['token' => $token]);
    }

    public function confirmar(Request $request, string $token, ConfirmacaoDeEmail $confirmacao): RedirectResponse
    {
        $r = $confirmacao->confirmar($token, $request->ip(), $request->userAgent());
        if ($r === null) {
            return redirect()->route('intelligence.trial.create')->withErrors(['nome' => 'Este link de confirmação não vale mais (venceu ou já foi usado). Faça o cadastro de novo.']);
        }
        if (! $r['novo']) {
            return redirect()->route('intelligence.trial.novo-link')->with('enviado', true);
        }

        return $this->entrar($request, $r['trial'], $r['token'], true);
    }

    /** Link pessoal: autentica e vai para o upload. */
    public function acessar(Request $request, string $token): RedirectResponse
    {
        $trial = IntelligenceTrial::porToken($token);
        if (! $trial || ! $trial->user) {
            return redirect()->route('intelligence.trial.novo-link')->withErrors(['whatsapp' => 'Link inválido ou substituído. Peça um novo link abaixo.']);
        }

        return $this->entrar($request, $trial, $token, false);
    }

    public function novoLink(): View
    {
        return view('intelligence-trial.novo-link');
    }

    public function enviarNovoLink(NovoLinkRequest $request, RecuperacaoDeAcesso $recuperacao): RedirectResponse
    {
        $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $request->whatsappE164())->first();
        if ($trial) {
            $recuperacao->solicitar($trial);
        }

        // Mesma resposta exista a conta ou não: a tela nunca revela se o número tem teste e nunca mostra o link.
        return back()->with('enviado', true);
    }

    private function entrar(Request $request, IntelligenceTrial $trial, string $token, bool $novo): RedirectResponse
    {
        Auth::guard('web')->login($trial->user, true); // lembrar = sessão persistente de longa duração
        $request->session()->regenerate();
        if (config('intelligence_trial.inicio') === 'cadastro') {
            app(TrialCiclo::class)->iniciarContagem($trial); // idempotente: conta antiga, sem relógio, começa no próximo acesso; nunca reinicia
        }
        app(DadosDeExemplo::class)->aoAbrir($trial); // 1ª abertura: mostra os dados de exemplo; fora do prazo: apaga o que sobrou

        return redirect(IntelligenceImportResource::getUrl('index'))
            ->with('intelligence_link', route('intelligence.trial.entrar', $token))
            ->with('intelligence_novo', $novo);
    }

    /**
     * O cadastro recebe POST do site oravel.com.br sem token CSRF. Aceita só quando Origin/Referer é o próprio app ou uma origem
     * permitida (config intelligence_site.origens_permitidas). Navegador que não manda nenhum dos dois passa (honeypot e throttle seguem valendo).
     */
    private function origemPermitida(Request $request): bool
    {
        $origem = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (! $origem || $origem === 'null') {
            return true;
        }
        $partes = parse_url($origem);
        if (empty($partes['host'])) {
            return false;
        }
        $base = ($partes['scheme'] ?? 'https').'://'.$partes['host'].(isset($partes['port']) ? ':'.$partes['port'] : '');
        $permitidas = array_merge((array) config('intelligence_site.origens_permitidas'), [$request->getSchemeAndHttpHost(), rtrim((string) config('intelligence_site.app_url'), '/')]);

        return in_array($base, $permitidas, true);
    }
}
