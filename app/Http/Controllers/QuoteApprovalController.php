<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pagina publica (sem login) de aprovacao de orçamento -- mesmo padrao de
 * portaria.verificar/{token}. O cliente final abre o link, ve o orçamento
 * e aprova/reprova direto, sem precisar de conta no sistema.
 */
class QuoteApprovalController extends Controller
{
    public function show(string $token): View
    {
        $quote = Quote::where('approval_token', $token)
            ->with(['client', 'items'])
            ->first();

        $quote?->markViewedByClient();

        return view('quotes.public-approval', ['quote' => $quote]);
    }

    public function approve(string $token): RedirectResponse
    {
        $quote = Quote::where('approval_token', $token)->firstOrFail();

        try {
            $quote->approve();
        } catch (\RuntimeException $e) {
            // ja' respondido ou fora do estagio esperado -- a view mostra
            // o status atual de qualquer jeito, sem quebrar a pagina.
        }

        return redirect()->route('quotes.public-approval', $token);
    }

    public function reject(string $token, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $quote = Quote::where('approval_token', $token)->firstOrFail();

        try {
            $quote->reject($data['reason']);
        } catch (\RuntimeException $e) {
            // idem approve() acima.
        }

        return redirect()->route('quotes.public-approval', $token);
    }
}
