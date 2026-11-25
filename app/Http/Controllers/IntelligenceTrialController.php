<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\TrialCadastro;
use App\Filament\Resources\IntelligenceImportResource;
use App\Http\Requests\IntelligenceTrial\CadastroTrialRequest;
use App\Http\Requests\IntelligenceTrial\NovoLinkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/** Cadastro rápido sem senha do teste grátis do Intelligence, entrada por link pessoal e novo link por WhatsApp. */
class IntelligenceTrialController extends Controller
{
    public function create(): View
    {
        return view('intelligence-trial.cadastro');
    }

    public function store(CadastroTrialRequest $request, TrialCadastro $cadastro): RedirectResponse
    {
        $r = $cadastro->cadastrar([
            'nome' => trim($request->string('nome')),
            'empresa' => trim($request->string('empresa')),
            'whatsapp' => $request->whatsappE164(),
        ], $request->ip(), $request->userAgent());

        return $this->entrar($request, $r['trial'], $r['token'], $r['novo']);
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

    public function enviarNovoLink(NovoLinkRequest $request): RedirectResponse
    {
        $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $request->whatsappE164())->first();
        if (! $trial) {
            return back()->withInput()->withErrors(['whatsapp' => 'Não encontramos um teste com esse WhatsApp.']);
        }

        $token = $trial->novoTokenDeAcesso();
        Log::info('Intelligence trial: novo link de acesso pedido.', ['trial_id' => $trial->id]);

        // Sem canal de envio ao cliente (sem WhatsApp da Oravel e sem e-mail no cadastro), o link só pode ser mostrado.
        if (config('intelligence_trial.mostrar_link_na_tela')) {
            return back()->with('link', route('intelligence.trial.entrar', $token));
        }

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
