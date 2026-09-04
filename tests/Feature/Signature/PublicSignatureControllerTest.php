<?php

namespace Tests\Feature\Signature;

use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use Tests\TestCase;

class PublicSignatureControllerTest extends TestCase
{
    private Tenant $tenant;
    private Contract $contract;
    private DocumentSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->contract = Contract::factory()->for($this->tenant)->create();
        $this->signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function can_view_signature_form()
    {
        $response = $this->get(route('signature.sign', ['token' => $this->signature->token]));

        $response->assertStatus(200);
        $response->assertViewIs('signature.form');
        $response->assertViewHas('signature', $this->signature);
        $response->assertViewHas('document', $this->contract);
    }

    /** @test */
    public function cannot_view_expired_signature()
    {
        $expiredSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get(route('signature.sign', ['token' => $expiredSignature->token]));

        $response->assertStatus(200);
        $response->assertViewIs('signature.error');
    }

    /** @test */
    public function can_submit_signature()
    {
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->post(route('signature.store', ['token' => $this->signature->token]), [
            'signature_base64' => "data:image/png;base64,{$pngBase64}",
            'signer_name' => 'João Silva',
            'signer_document' => '123.456.789-00',
            'signer_email' => 'joao@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->signature->refresh();
        $this->assertTrue($this->signature->is_signed);
    }

    /** @test */
    public function cannot_submit_without_signature_image()
    {
        $response = $this->post(route('signature.store', ['token' => $this->signature->token]), [
            'signature_base64' => '',
            'signer_name' => 'João Silva',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function can_view_success_page()
    {
        $this->signature->update(['status' => 'signed', 'signed_at' => now()]);

        $response = $this->get(route('signature.success', ['token' => $this->signature->token]));

        $response->assertStatus(200);
        $response->assertViewIs('signature.success');
        $response->assertViewHas('signature', $this->signature);
    }

    /** @test */
    public function cannot_download_unsigned_document()
    {
        $response = $this->get(route('signature.download', ['token' => $this->signature->token]));

        $response->assertStatus(404);
    }

    /** @test */
    public function is_public_without_authentication()
    {
        // Testa que rotas de assinatura não requerem autenticação
        $response = $this->get(route('signature.sign', ['token' => $this->signature->token]));

        // Sem middleware auth, deve retornar 200 (ou 404 se token inválido)
        $this->assertIn($response->status(), [200, 404]);
    }
}
