<?php

namespace App\Http\Controllers;

use App\Models\CrmProposta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Link público da proposta do CRM (sem login): o cliente vê, aceita ou recusa. O token não é adivinhável. */
class CrmPropostaPublicaController extends Controller
{
    private function achar(string $token): CrmProposta
    {
        return CrmProposta::withoutGlobalScopes()
            ->whereIn('status', [CrmProposta::STATUS_ENVIADA, CrmProposta::STATUS_ACEITA, CrmProposta::STATUS_RECUSADA, CrmProposta::STATUS_EXPIRADA])
            ->where('token', $token)
            ->with(['itens', 'conta', 'lead', 'vendedor', 'tenant'])
            ->firstOrFail();
    }

    public function show(string $token)
    {
        $proposta = $this->achar($token);

        // Vencida e ainda "enviada": mostra como expirada sem esperar o comando diário.
        if ($proposta->status === CrmProposta::STATUS_ENVIADA && $proposta->estaVencida()) {
            $proposta->update(['status' => CrmProposta::STATUS_EXPIRADA]);
        }

        $proposta->marcarVisualizada();

        return view('crm-proposta.publica', ['proposta' => $proposta]);
    }

    public function aceitar(Request $request, string $token): RedirectResponse
    {
        $dados = $request->validate(['nome' => ['required', 'string', 'min:3', 'max:120']]);
        $proposta = $this->achar($token);

        try {
            $proposta->aceitar(trim($dados['nome']), $request->ip());
        } catch (\RuntimeException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Proposta aceita. Obrigado! O vendedor entrará em contato.');
    }

    public function recusar(Request $request, string $token): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:1000']]);
        $proposta = $this->achar($token);

        try {
            $proposta->recusar(trim($dados['motivo']), $request->ip());
        } catch (\RuntimeException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Resposta registrada. Obrigado pelo retorno.');
    }
}
