<?php

namespace App\Http\Controllers;

use App\Services\SignatureService;
use Illuminate\Http\Request;
use Throwable;

class PublicSignatureController extends Controller
{
    public function __construct(
        private SignatureService $signatureService
    ) {}

    /**
     * Exibe formulário de assinatura via token único.
     */
    public function show(string $token)
    {
        try {
            $signature = $this->signatureService->getSignatureByToken($token);

            // Carrega documento relacionado (Contract ou MaintenanceOrder)
            $document = $signature->signable;

            return view('signature.form', compact('signature', 'document', 'token'));
        } catch (Throwable $e) {
            return view('signature.error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Processa submissão de assinatura.
     */
    public function store(Request $token, Request $request)
    {
        try {
            $validated = $request->validate([
                'signature_base64' => 'required|string',
                'signer_name' => 'required|string|max:255',
                'signer_document' => 'nullable|string|max:20',
                'signer_email' => 'nullable|email',
                'signer_phone' => 'nullable|string|max:20',
            ]);

            // Adiciona IP e User-Agent automaticamente
            $validated['ip_address'] = $request->ip();
            $validated['user_agent'] = $request->userAgent();

            // Geolocalização será capturada via JavaScript frontend
            // Se o frontend enviar latitude/longitude, adicionar aqui
            if ($request->has('geolocation')) {
                $validated['geolocation'] = $request->input('geolocation');
            }

            // Processa assinatura
            $this->signatureService->signDocument($token, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Assinatura realizada com sucesso!',
                'redirect' => route('signature.success', ['token' => $token]),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Página de sucesso após assinatura.
     */
    public function success(string $token)
    {
        try {
            $signature = $this->signatureService->getSignatureByToken($token);

            if (!$signature->is_signed) {
                return view('signature.error', [
                    'message' => 'Esta assinatura ainda não foi processada.',
                ]);
            }

            return view('signature.success', compact('signature'));
        } catch (Throwable $e) {
            return view('signature.error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Download do PDF assinado.
     */
    public function download(string $token)
    {
        try {
            $signature = $this->signatureService->getSignatureByToken($token);

            if (!$signature->is_signed) {
                abort(404, 'Documento não assinado ainda.');
            }

            $document = $signature->signable;

            // Gera PDF final (com página de auditoria)
            $pdfPath = $this->signatureService->finalizeSignedPdf($document);

            if (!$pdfPath) {
                abort(500, 'Erro ao gerar PDF.');
            }

            return response()->download(
                \Storage::disk('s3')->path($pdfPath),
                sprintf('%s_%s_assinado.pdf', class_basename($document), $document->id)
            );
        } catch (Throwable $e) {
            abort(500, $e->getMessage());
        }
    }
}
