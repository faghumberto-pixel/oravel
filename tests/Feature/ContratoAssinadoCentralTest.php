<?php

namespace Tests\Feature;

use App\Models\DocumentSignature;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoAssinadoCentralTest extends TestCase
{
    use DatabaseTransactions;

    private function assinatura(): DocumentSignature
    {
        $tenant = Tenant::withoutGlobalScopes()->create(['name' => 'Cliente Teste PDF', 'slug' => 'cliente-teste-pdf-'.uniqid()]);

        return DocumentSignature::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Tenant::class,
            'signable_id' => $tenant->id,
            'token' => bin2hex(random_bytes(16)),
            'signer_name' => 'Fulano de Tal',
            'signer_document' => '000.000.000-00',
            'signer_email' => 'fulano@example.com',
            'ip_address' => '127.0.0.1',
            'status' => 'signed',
            'signed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_gera_guarda_e_reaproveita_o_pdf_assinado(): void
    {
        Storage::fake('local');
        $assinatura = $this->assinatura();

        $path = app(SignatureService::class)->ensureSignedPdf($assinatura);

        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($path));
        $this->assertSame($path, $assinatura->fresh()->signed_pdf_path);

        $conteudo = Storage::disk('local')->get($path);
        $this->assertSame($path, app(SignatureService::class)->ensureSignedPdf($assinatura->fresh()));
        $this->assertSame($conteudo, Storage::disk('local')->get($path), 'o arquivo guardado nao pode mudar');
    }

    public function test_so_super_admin_ve_e_baixa(): void
    {
        Storage::fake('local');
        $assinatura = $this->assinatura();
        $comum = User::factory()->create();

        $this->actingAs($comum)->get(route('central.contrato-assinado', $assinatura->id))->assertForbidden();
    }
}
