<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Notifications\ClientMagicLinkNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * "Entrar sem senha" do Portal do Cliente (guard 'client', ver
 * ClientPanelProvider). Fluxo: cliente pede o link -> e-mail com URL
 * assinada e de uso único -> clique loga automaticamente no guard 'client'.
 *
 * Uso único de verdade (não só a assinatura/expiração do
 * temporarySignedRoute, que sozinha permite reuso dentro da janela): o
 * token real de posse é um valor aleatório guardado no Cache com TTL de
 * 15min, apagado no primeiro uso -- a URL assinada é só uma camada extra
 * contra adulteração do token/e-mail na querystring.
 */
class ClientMagicLinkController extends Controller
{
    private const TTL_MINUTES = 15;

    public function create()
    {
        return view('client.magic-link.request');
    }

    public function send(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $client = Client::where('email', $request->input('email'))->first();

        // Nunca revela se o e-mail existe ou não -- mesma mensagem de sucesso
        // em ambos os casos (mesmo padrão de UX de "esqueci minha senha").
        if ($client && $client->portal_access_enabled_at) {
            $token = Str::random(64);

            Cache::put("client-magic-link:{$token}", $client->id, now()->addMinutes(self::TTL_MINUTES));

            $signedUrl = URL::temporarySignedRoute(
                'cliente.magic-link.login',
                now()->addMinutes(self::TTL_MINUTES),
                ['token' => $token]
            );

            $client->notify(new ClientMagicLinkNotification($signedUrl));
        }

        return back()->with('status', 'Se o e-mail informado tiver acesso ao portal, enviamos um link de entrada para ele.');
    }

    public function login(Request $request, string $token)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link inválido ou expirado.');
        }

        $clientId = Cache::pull("client-magic-link:{$token}");

        if (! $clientId) {
            abort(403, 'Este link já foi usado ou expirou. Solicite um novo.');
        }

        $client = Client::find($clientId);

        if (! $client || ! $client->portal_access_enabled_at) {
            abort(403, 'Acesso ao portal não disponível para esta conta.');
        }

        Auth::guard('client')->login($client);

        $request->session()->regenerate();

        return redirect()->to('/cliente');
    }
}
