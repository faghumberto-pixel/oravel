<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\RecuperacaoDeAcesso;
use App\Domain\Intelligence\Trial\Services\TrialCadastro;
use App\Filament\Resources\IntelligenceImportResource;
use App\Http\Requests\IntelligenceTrial\CadastroTrialRequest;
use App\Http\Requests\IntelligenceTrial\NovoLinkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Cadastro rápido sem senha do teste grátis do Intelligence, entrada por link pessoal e novo link por WhatsApp. */
class IntelligenceTrialController extends Controller
{
    public function create(): View
    {
        return view('intelligence-trial.cadastro');
    }

    public function store(CadastroTrialRequest $request, TrialCadastro $cadastro, RecuperacaoDeAcesso $recuperacao): RedirectResponse
    {
        $r = $cadastro->cadastrar([
            'nome' => trim($request->string('nome')),
            'empresa' => trim($request->string('empresa')),
            'whatsapp' => $request->whatsappE164(),
        ], $request->ip(), $request->userAgent());

        if (! $r['novo']) {
            // WhatsApp que já tem conta: NÃO loga e NÃO mostra link (quem digitou pode não ser o dono do número).
            $recuperacao->solicitar($r['trial']);

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

        return redirect(IntelligenceImportResource::getUrl('index'))
            ->with('intelligence_link', route('intelligence.trial.entrar', $token))
            ->with('intelligence_novo', $novo);
    }
}
