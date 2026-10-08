<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato de assinatura (Oravel x cliente) já assinado, para a Central
 * visualizar como página ou baixar o PDF. Só administrador da plataforma.
 */
class ContratoAssinadoController extends Controller
{
    /** Mostra o contrato assinado como página, antes de abrir ou baixar o PDF. */
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

    public function __invoke(Request $request, string $signature, SignatureService $service): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $assinatura = $this->assinatura($signature);

        $path = $service->ensureSignedPdf($assinatura);
        $cliente = Str::slug(Tenant::withoutGlobalScopes()->find($assinatura->signable_id)?->name ?? 'cliente');
        $nome = "contrato-assinado-{$cliente}.pdf";
        $disk = Storage::disk('local');

        return $request->boolean('baixar')
            ? $disk->download($path, $nome, ['Content-Type' => 'application/pdf'])
            : response($disk->get($path), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=\"{$nome}\""]);
    }
}
