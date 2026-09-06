<?php

namespace App\Services;

use App\Models\DocumentSignature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SignatureService
{
    private const SIGNATURE_DISK = 's3'; // ou 'local' se preferir

    /**
     * Gera um link seguro de assinatura via token único.
     *
     * @param Model $signable Contract ou MaintenanceOrder
     * @param array $signerData {name, document?, email?, phone?}
     * @return string URL de assinatura
     */
    public function generateSignatureLink(Model $signable, array $signerData): string
    {
        $signature = DocumentSignature::create([
            'tenant_id' => $signable->tenant_id,
            'signable_type' => $signable::class,
            'signable_id' => $signable->id,
            'signer_name' => $signerData['name'] ?? 'Unnamed',
            'signer_document' => $signerData['document'] ?? null,
            'signer_email' => $signerData['email'] ?? null,
            'signer_phone' => $signerData['phone'] ?? null,
        ]);

        return route('signature.sign', ['token' => $signature->token]);
    }

    /**
     * Recupera assinatura por token e valida expiração.
     *
     * @throws Throwable
     */
    public function getSignatureByToken(string $token): DocumentSignature
    {
        $signature = DocumentSignature::byToken($token)->firstOrFail();

        if ($signature->is_expired) {
            $signature->markAsExpired();
            throw new \Exception('Assinatura expirou. Solicite um novo link.');
        }

        if (!$signature->can_sign) {
            throw new \Exception('Esta assinatura não pode mais ser processada.');
        }

        return $signature;
    }

    /**
     * Processa assinatura do documento.
     *
     * - Valida token e expiração
     * - Salva imagem PNG da assinatura no Storage
     * - Registra IP, User-Agent e geolocalização
     * - Marca como assinado
     * - Dispara eventos para finalização de PDF
     *
     * @param string $token Token único de assinatura
     * @param array $data {
     *     signature_base64: string PNG em base64,
     *     signer_name: string,
     *     signer_document?: string,
     *     ip_address?: string,
     *     user_agent?: string,
     *     geolocation?: array {lat, lng, accuracy}
     * }
     */
    public function signDocument(string $token, array $data): bool
    {
        try {
            $signature = $this->getSignatureByToken($token);

            // Valida base64 da assinatura
            if (empty($data['signature_base64'])) {
                throw new \Exception('Assinatura não fornecida.');
            }

            // Atualiza nome do signatário se fornecido
            if (!empty($data['signer_name'])) {
                $signature->signer_name = $data['signer_name'];
            }

            // Processa e salva imagem PNG da assinatura
            $imagePath = $this->saveSignatureImage(
                $signature->id,
                $signature->tenant_id,
                $data['signature_base64']
            );

            // Registra metadados de assinatura
            $signature->update([
                'signature_image_path' => $imagePath,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'geolocation' => $data['geolocation'] ?? null,
            ]);

            // Marca como assinado
            $signature->markAsSigned();

            // Dispara evento para finalizar PDF (observer ou job)
            event(new \App\Events\DocumentSigned($signature));

            return true;
        } catch (Throwable $e) {
            \Log::error('Erro ao assinar documento', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Salva imagem PNG da assinatura no Storage (S3 ou local).
     */
    private function saveSignatureImage(string $signatureId, string $tenantId, string $base64Data): string
    {
        try {
            // Remove prefixo data:image/png;base64, se presente
            $imageData = str_replace('data:image/png;base64,', '', $base64Data);

            // Decodifica base64
            $decodedImage = base64_decode($imageData, true);

            if ($decodedImage === false) {
                throw new \Exception('Base64 inválido.');
            }

            // Define caminho no Storage
            $filename = "{$tenantId}/{$signatureId}.png";
            $path = "signatures/{$filename}";

            // Salva no disco configurado
            Storage::disk(self::SIGNATURE_DISK)->put($path, $decodedImage);

            return $path;
        } catch (Throwable $e) {
            \Log::error('Erro ao salvar imagem de assinatura', [
                'signature_id' => $signatureId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Finaliza PDF com página de auditoria (metadados, carimbo de data/hora, hash).
     *
     * Adiciona uma página ao final do PDF com:
     * - Nome e CPF/CNPJ do signatário
     * - Data e hora de assinatura
     * - Localização (IP, geolocalização)
     * - Hash SHA-256 do documento
     * - URL de verificação (QR code opcional)
     *
     * @param Model $signable Contract ou MaintenanceOrder
     */
    public function finalizeSignedPdf(Model $signable): ?string
    {
        try {
            // Recupera assinatura do documento
            $signature = $signable->signedSignatures()->latest()->first();

            if (!$signature) {
                return null;
            }

            // Gera PDF do documento original (implementação específica do cliente)
            $originalPdf = $this->generateDocumentPdf($signable);

            // Gera página de auditoria
            $auditPage = $this->generateAuditPage($signature);

            // Combina PDFs (original + auditoria)
            $finalPdf = $this->mergePdfs($originalPdf, $auditPage);

            // Calcula hash SHA-256 do PDF final
            $hash = hash('sha256', $finalPdf);

            // Salva hash no banco
            $signature->update(['document_hash' => $hash]);

            // Salva PDF final no Storage
            $pdfPath = $this->saveFinalPdf($signable, $signature->id, $finalPdf);

            return $pdfPath;
        } catch (Throwable $e) {
            \Log::error('Erro ao finalizar PDF assinado', [
                'signable_type' => $signable::class,
                'signable_id' => $signable->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Gera PDF do documento (Contract ou MaintenanceOrder).
     *
     * Implementação específica do Oravel -- pode chamar view personalizada.
     */
    private function generateDocumentPdf(Model $signable): string
    {
        // Implementar de acordo com o tipo de documento
        // Por enquanto, retorna PDF genérico
        $view = match ($signable::class) {
            \App\Models\Contract::class => 'documents.contract-pdf',
            \App\Models\MaintenanceOrder::class => 'documents.maintenance-order-pdf',
            default => 'documents.generic-pdf',
        };

        return Pdf::loadView($view, ['document' => $signable])
            ->output();
    }

    /**
     * Gera página de auditoria em PDF.
     */
    private function generateAuditPage(DocumentSignature $signature): string
    {
        return Pdf::loadView('documents.signature-audit-page', [
            'signature' => $signature,
            'signerLocation' => $signature->geolocation ? sprintf(
                'Latitude: %s, Longitude: %s',
                $signature->geolocation['lat'] ?? 'N/A',
                $signature->geolocation['lng'] ?? 'N/A'
            ) : 'Não capturada',
        ])->output();
    }

    /**
     * Mescla dois PDFs (original + auditoria).
     *
     * Usa setasign/fpdi para importar páginas do PDF original
     * e adiciona a página de auditoria ao final.
     */
    private function mergePdfs(string $originalPdf, string $auditPdf): string
    {
        try {
            $pdf = new \setasign\Fpdi\Fpdi();

            // Importa páginas do PDF original
            $originalPageCount = $pdf->setSourceFile(\Illuminate\Support\Facades\File::tempnam(
                sys_get_temp_dir(),
                'pdf'
            ));

            // Escreve conteúdo temporário do PDF original
            file_put_contents($tempOriginal = tempnam(sys_get_temp_dir(), 'pdf'), $originalPdf);
            $pageCount = $pdf->setSourceFile($tempOriginal);

            // Copia todas as páginas do documento original
            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Escreve conteúdo temporário do PDF de auditoria
            file_put_contents($tempAudit = tempnam(sys_get_temp_dir(), 'pdf'), $auditPdf);
            $auditPageCount = $pdf->setSourceFile($tempAudit);

            // Adiciona página de auditoria
            if ($auditPageCount > 0) {
                $templateId = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Gera PDF final
            $mergedPdf = $pdf->Output(null, 'S');

            // Limpa arquivos temporários
            @unlink($tempOriginal);
            @unlink($tempAudit);

            return $mergedPdf;
        } catch (Throwable $e) {
            \Log::error('Erro ao mesclar PDFs', [
                'error' => $e->getMessage(),
            ]);

            // Fallback: retorna PDF original se merge falhar
            return $originalPdf;
        }
    }

    /**
     * Salva PDF final no Storage.
     */
    private function saveFinalPdf(Model $signable, string $signatureId, string $pdfContent): string
    {
        $filename = sprintf(
            '%s_%s_%s.pdf',
            class_basename($signable),
            $signable->id,
            now()->format('Y-m-d-His')
        );

        $path = "signed-documents/{$signable->tenant_id}/{$filename}";

        Storage::disk(self::SIGNATURE_DISK)->put($path, $pdfContent);

        return $path;
    }

    /**
     * Cancela assinatura pendente (ex: via admin).
     */
    public function cancelSignature(DocumentSignature $signature): bool
    {
        if (!$signature->is_pending) {
            throw new \Exception('Apenas assinaturas pendentes podem ser canceladas.');
        }

        $signature->markAsCanceled();
        return true;
    }

    /**
     * Revalida token (estende expiração).
     */
    public function renewSignatureToken(DocumentSignature $signature, int $daysToAdd = 30): bool
    {
        if (!$signature->is_pending) {
            throw new \Exception('Apenas assinaturas pendentes podem ser renovadas.');
        }

        $signature->update([
            'expires_at' => $signature->expires_at->addDays($daysToAdd),
        ]);

        return true;
    }
}
