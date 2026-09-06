<?php

namespace Tests\Feature;

use App\Services\SignatureService;
use Tests\TestCase;

class SignaturePdfMergeTest extends TestCase
{
    private SignatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SignatureService::class);
    }

    public function test_merge_pdfs_handles_base_pdfs()
    {
        // Cria PDFs mínimos válidos
        $originalPdf = $this->createMinimalPdf('Original Document');
        $auditPdf = $this->createMinimalPdf('Audit Page');

        // Usa reflection para acessar método privado
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('mergePdfs');
        $method->setAccessible(true);

        $mergedPdf = $method->invoke($this->service, $originalPdf, $auditPdf);

        // Valida que PDF foi gerado
        $this->assertNotEmpty($mergedPdf);
        $this->assertStringStartsWith('%PDF', $mergedPdf);
    }

    /**
     * Cria um PDF mínimo válido para testes
     */
    private function createMinimalPdf(string $text): string
    {
        $pdf = <<<PDF
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>
endobj
4 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
5 0 obj
<< /Length 44 >>
stream
BT
/F1 12 Tf
50 750 Td
($text) Tj
ET
endstream
endobj
xref
0 6
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000229 00000 n
0000000322 00000 n
trailer
<< /Size 6 /Root 1 0 R >>
startxref
416
%%EOF
PDF;
        return $pdf;
    }
}
