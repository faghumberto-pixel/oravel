<?php

namespace App\Http\Controllers;

use App\Models\PropostaComercial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página pública (sem login) de aprovação de proposta comercial pelo
 * cliente -- réplica direta de QuoteApprovalController, mesmo padrão de
 * portaria.verificar/{token}.
 */
class PropostaComercialApprovalController extends Controller
{
    public function show(string $token): View
    {
        $proposta = PropostaComercial::where('approval_token', $token)
            ->with(['client', 'items'])
            ->first();

        $proposta?->markViewedByClient();

        return view('proposta-comercial.public-approval', ['proposta' => $proposta]);
    }

    public function approve(string $token): RedirectResponse
    {
        $proposta = PropostaComercial::where('approval_token', $token)->firstOrFail();

        try {
            $proposta->aceitarPeloCliente();
        } catch (\RuntimeException $e) {
            // já respondido ou fora do estágio esperado -- a view mostra
            // o status atual de qualquer jeito, sem quebrar a página.
        }

        return redirect()->route('proposta-comercial.public-approval', $token);
    }

    public function reject(string $token, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $proposta = PropostaComercial::where('approval_token', $token)->firstOrFail();

        try {
            $proposta->recusarPeloCliente($data['reason']);
        } catch (\RuntimeException $e) {
            // idem approve() acima.
        }

        return redirect()->route('proposta-comercial.public-approval', $token);
    }
}
