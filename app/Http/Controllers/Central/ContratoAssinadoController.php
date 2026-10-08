<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato de assinatura (Oravel x cliente) já assinado, para a Central
 * visualizar como página e imprimir. Só administrador da plataforma.
 */
class ContratoAssinadoController extends Controller
{
    /** Mostra o contrato assinado como página. */
    public function ver(Request $request, string $signature): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $assinatura = $this->assinatura($signature);
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($assinatura->signable_id);

        $contrato = view('pdf.subscription-agreement', ['contract' => $tenant])->render();
        $auditoria = view('documents.signature-audit-page', [
            'signature' => $assinatura,
            'signerLocation' => $assinatura->geolocation
                ? sprintf('Latitude: %s, Longitude: %s', $assinatura->geolocation['lat'] ?? 'N/A', $assinatura->geolocation['lng'] ?? 'N/A')
                : 'Não capturada',
        ])->render();

        return response()->view('central.contrato-assinado', [
            'assinatura' => $assinatura,
            'cliente' => $tenant,
            'contrato' => $contrato,
            'auditoria' => $auditoria,
        ]);
    }

    private function assinatura(string $id): DocumentSignature
    {
        return DocumentSignature::withoutGlobalScopes()
            ->where('signable_type', Tenant::class)
            ->where('status', 'signed')
            ->findOrFail($id);
    }
}
