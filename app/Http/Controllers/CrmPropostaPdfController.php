<?php

namespace App\Http\Controllers;

use App\Filament\Resources\CrmPropostaResource;
use Illuminate\Support\Facades\Gate;

/** Baixa o PDF da proposta do CRM (só quem pode ver a proposta: vendedor vê as dele; "ver todos" vê as da empresa). */
class CrmPropostaPdfController extends Controller
{
    public function __invoke(string $id)
    {
        $proposta = CrmPropostaResource::getEloquentQuery()->findOrFail($id);
        Gate::authorize('view', $proposta);

        return response()->streamDownload(fn () => print ($proposta->gerarPdf()), "proposta-{$proposta->numero}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
