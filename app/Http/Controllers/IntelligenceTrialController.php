<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Trial\Services\ConfirmacaoDeEmail;
use App\Domain\Intelligence\Trial\Services\Turnstile;
use App\Http\Requests\IntelligenceTrial\CadastroTrialRequest;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Cadastro do teste grátis do Intelligence: e-mail confirmado + senha. O acesso é sempre pela tela de login (e-mail e senha). */
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
            'senha' => (string) $request->input('senha'),
            'objetivo' => $request->input('objetivo') ?: null,
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

    /** Confirma o e-mail e cria a conta. NÃO entra sozinho: a pessoa entra pela tela de login, com o e-mail e a senha que criou. */
    public function confirmar(Request $request, string $token, ConfirmacaoDeEmail $confirmacao): RedirectResponse
    {
        $r = $confirmacao->confirmar($token, $request->ip(), $request->userAgent());
        if ($r === null) {
            return redirect()->route('intelligence.trial.create')->withErrors(['nome' => 'Este link de confirmação não vale mais (venceu ou já foi usado). Faça o cadastro de novo.']);
        }

        return redirect(Filament::getPanel('admin')->getLoginUrl(['confirmado' => 1]));
    }

    /** Links antigos de acesso direto (e-mails já enviados) não entram mais: levam à tela de entrada. */
    public function acessoPorLinkEncerrado(): RedirectResponse
    {
        return redirect(Filament::getPanel('admin')->getLoginUrl(['confirmado' => 0]));
    }

    /** "Receber um novo link" virou "Esqueci minha senha". */
    public function esqueciASenha(): RedirectResponse
    {
        return redirect()->route('password.request');
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
