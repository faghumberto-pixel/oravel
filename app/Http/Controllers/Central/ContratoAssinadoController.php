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

        return response()->view('documentos.visualizar', [
            'titulo' => "Contrato assinado — {$tenant->name}",
            'voltar' => \App\Filament\Central\Resources\ContratoAssinadoResource::getUrl('index', panel: 'central'),
            'resumo' => [
                'Assinado por' => $assinatura->signer_name,
                'CPF/CNPJ' => $assinatura->signer_document ?: '—',
                'E-mail' => $assinatura->signer_email ?: '—',
                'Assinado em' => $assinatura->signed_at?->format('d/m/Y H:i'),
                'IP' => $assinatura->ip_address ?: '—',
                'Código de segurança' => $assinatura->document_hash ? substr($assinatura->document_hash, 0, 24).'…' : '—',
            ],
            'secoes' => [
                ['titulo' => 'Contrato', 'html' => $contrato],
                ['titulo' => 'Comprovante de assinatura', 'html' => $auditoria, 'nova_pagina' => true],
            ],
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
