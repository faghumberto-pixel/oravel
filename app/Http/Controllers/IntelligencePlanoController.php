<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Trial\Models\IntelligenceContrato;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\AssinaturaPlano;
use App\Domain\Intelligence\Trial\Services\ContratoPlano;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Http\Requests\IntelligenceTrial\ContratarPlanoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Plano básico dentro do sistema: ver situação, contratar (com aceite do contrato), cancelar e baixar o contrato aceito. */
class IntelligencePlanoController extends Controller
{
    public function show(Request $request): View
    {
        $trial = $this->trial($request);

        return view('intelligence-trial.plano', ['trial' => $trial, 'status' => $trial->statusAtual(), 'contrato' => $trial->contratos()->latest('aceite_em')->first()]);
    }

    public function contratar(Request $request, ContratoPlano $contratos): View|RedirectResponse
    {
        $trial = $this->trial($request);
        if ($trial->contratou() && ! $trial->canceled_at) {
            return redirect()->route('intelligence.trial.plano');
        }
        $inicio = ContratoPlano::inicioDaCobranca($trial);
        $previa = $contratos->renderizar([
            'razao_social' => old('razao_social', '______'), 'cnpj' => old('cnpj', '______'), 'email' => old('email', '______'), 'responsavel' => old('responsavel', '______'),
        ], $inicio);

        return view('intelligence-trial.contratar', ['trial' => $trial, 'inicio' => $inicio, 'texto' => $previa['texto']]);
    }

    public function salvar(ContratarPlanoRequest $request, AssinaturaPlano $assinaturas): RedirectResponse
    {
        $trial = $this->trial($request);

        try {
            $assinaturas->contratar($trial, $request->validated(), $request->ip(), $request->userAgent());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['geral' => $e->getMessage()]);
        }

        return redirect()->route('intelligence.trial.plano')->with('ok', 'Plano contratado. Enviamos uma cópia do contrato para o seu e-mail.');
    }

    public function cancelar(Request $request, AssinaturaPlano $assinaturas): RedirectResponse
    {
        try {
            $assinaturas->cancelar($this->trial($request));
        } catch (\DomainException $e) {
            return back()->withErrors(['geral' => $e->getMessage()]);
        }

        return redirect()->route('intelligence.trial.plano')->with('ok', 'Plano cancelado. Você mantém o acesso até o fim do período pago.');
    }

    public function contratoPdf(Request $request, string $id, ContratoPlano $contratos)
    {
        $trial = $this->trial($request);
        $c = IntelligenceContrato::withoutGlobalScopes()->where('trial_id', $trial->id)->findOrFail($id);
        $caminho = $c->pdf_path && Storage::disk('local')->exists($c->pdf_path) ? $c->pdf_path : $c->tap(fn ($c) => $c->update(['pdf_path' => $contratos->gerarPdf($c)]))->pdf_path;

        return Storage::disk('local')->download($caminho, 'contrato-oravel-intelligence.pdf');
    }

    private function trial(Request $request): IntelligenceTrial
    {
        return TrialCiclo::doTenant($request->user()->tenant_id) ?? abort(404);
    }
}
