<?php

namespace App\Http\Controllers;

use App\Support\Campo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class CampoController extends Controller
{
    public function home(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        abort_unless(Campo::ativaPara($user), 404);

        return view('campo.home', ['user' => $user, 'itens' => Campo::itens($user)]);
    }

    /** Alternância manual entre as versões; lembra a escolha por um ano. */
    public function alternar(Request $request, string $versao): RedirectResponse
    {
        abort_unless(in_array($versao, ['campo', 'completa'], true), 404);

        $destino = $versao === 'campo' ? route('campo.home') : url('/admin');

        return redirect($destino)->withCookie(Cookie::make(Campo::COOKIE, $versao, 60 * 24 * 365));
    }
}
