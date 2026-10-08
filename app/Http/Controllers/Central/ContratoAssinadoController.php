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
 * visualizar no navegador ou baixar. Só administrador da plataforma.
 */
class ContratoAssinadoController extends Controller
{
    public function __invoke(Request $request, string $signature, SignatureService $service): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $assinatura = DocumentSignature::withoutGlobalScopes()
            ->where('signable_type', Tenant::class)
            ->where('status', 'signed')
            ->findOrFail($signature);

        $path = $service->ensureSignedPdf($assinatura);
        $cliente = Str::slug(Tenant::withoutGlobalScopes()->find($assinatura->signable_id)?->name ?? 'cliente');
        $nome = "contrato-assinado-{$cliente}.pdf";
        $disk = Storage::disk('local');

        return $request->boolean('baixar')
            ? $disk->download($path, $nome, ['Content-Type' => 'application/pdf'])
            : response($disk->get($path), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=\"{$nome}\""]);
    }
}
