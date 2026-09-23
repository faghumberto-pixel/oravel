<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
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
     *
     * BUG REAL corrigido 2026-09-23: a assinatura antiga era
     * `store(Request $token, Request $request)` -- como os dois parâmetros
     * tinham o MESMO type-hint de classe (Request), a resolução de
     * dependências do Laravel (Route::resolveMethodDependencies) fazia
     * array_splice() pra injetar a instância de Request na posição do
     * primeiro parâmetro, e isso reindexava o array associativo de route
     * params (que tinha a chave 'token'), empurrando a string do token pra
     * posição do SEGUNDO parâmetro -- ou seja, $token recebia o objeto
     * Request e $request recebia a string do token, o oposto do que o
     * nome de cada variável sugeria. Resultado: TypeError 500 em toda
     * tentativa de assinar (sem exceção -- Contract, MaintenanceOrder,
     * Tenant), só não pego antes porque o único teste que exercitava esta
     * rota (PublicSignatureControllerTest) já falhava antes de chegar
     * aqui por um problema não relacionado (Tenant sem Factory). Corrigido
     * tipando $token como string simples (casa por nome com a rota) em vez
     * de Request.
     */
    public function store(string $token, Request $request)
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

            // Captura o documento assinável ANTES de assinar -- depois de
            // signDocument() a assinatura já fica com status 'signed', e
            // getSignatureByToken() valida can_sign (status === 'pending')
            // de novo a cada chamada; chamá-lo de novo aqui (como uma
            // versão anterior fazia) lançava "Esta assinatura não pode
            // mais ser processada" bem na hora que a assinatura tinha
            // acabado de funcionar (achado real 2026-09-23, via teste).
            $signable = $this->signatureService->getSignatureByToken($token)->signable;

            // Processa assinatura
            $this->signatureService->signDocument($token, $validated);

            // Contrato de Assinatura do Tenant (2026-09-23, pedido do
            // usuário: "ele não pode pagar se não assinar o contrato") --
            // em vez da tela de sucesso genérica, segue direto pro Checkout
            // de pagamento (AsaasCheckoutController::continueAfterSignature()),
            // que só é acessível DEPOIS de confirmar que a assinatura foi
            // concluída -- não é uma etapa pulável.
            $redirect = $signable instanceof Tenant
                ? route('checkout.continue', ['token' => $token])
                : route('signature.success', ['token' => $token]);

            return response()->json([
                'success' => true,
                'message' => 'Assinatura realizada com sucesso!',
                'redirect' => $redirect,
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

            if (! $signature->is_signed) {
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

            if (! $signature->is_signed) {
                abort(404, 'Documento não assinado ainda.');
            }

            $document = $signature->signable;

            // Gera PDF final (com página de auditoria)
            $pdfPath = $this->signatureService->finalizeSignedPdf($document);

            if (! $pdfPath) {
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
