<?php

namespace Tests\Feature\Signature;

use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Services\SignatureService;
use Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    private SignatureService $service;
    private Tenant $tenant;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SignatureService::class);

        // Setup tenant e contract
        $this->tenant = Tenant::factory()->create();
        $this->contract = Contract::factory()->for($this->tenant)->create();
    }

    /** @test */
    public function can_generate_signature_link()
    {
        $signerData = [
            'name' => 'João Silva',
            'document' => '123.456.789-00',
            'email' => 'joao@example.com',
            'phone' => '(11) 99999-9999',
        ];

        $link = $this->service->generateSignatureLink($this->contract, $signerData);

        // Verifica que link foi gerado
        $this->assertStringContainsString('/assinatura/', $link);

        // Verifica que DocumentSignature foi criada
        $this->assertDatabaseHas('document_signatures', [
            'signable_type' => Contract::class,
            'signable_id' => $this->contract->id,
            'signer_name' => 'João Silva',
            'signer_document' => '123.456.789-00',
        ]);
    }

    /** @test */
    public function can_get_signature_by_token()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $retrieved = $this->service->getSignatureByToken($signature->token);

        $this->assertEquals($signature->id, $retrieved->id);
    }

    /** @test */
    public function throws_exception_for_expired_signature()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Assinatura expirou');

        $this->service->getSignatureByToken($signature->token);
    }

    /** @test */
    public function can_sign_document()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        // Gera uma imagem PNG simples em base64
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $result = $this->service->signDocument($signature->token, [
            'signature_base64' => "data:image/png;base64,{$pngBase64}",
            'signer_name' => 'João Silva',
            'signer_document' => '123.456.789-00',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertTrue($result);

        // Verifica que assinatura foi salva
        $this->assertDatabaseHas('document_signatures', [
            'id' => $signature->id,
            'status' => 'signed',
        ]);

        // Verifica que foi marcada como assinada
        $signature->refresh();
        $this->assertTrue($signature->is_signed);
        $this->assertNotNull($signature->signed_at);
    }

    /** @test */
    public function can_cancel_signature()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $result = $this->service->cancelSignature($signature);

        $this->assertTrue($result);

        $signature->refresh();
        $this->assertEquals('canceled', $signature->status);
    }

    /** @test */
    public function cannot_cancel_already_signed_signature()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'signed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Apenas assinaturas pendentes podem ser canceladas');

        $this->service->cancelSignature($signature);
    }

    /** @test */
    public function can_renew_signature_token()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(5),
        ]);

        $originalExpiration = $signature->expires_at;

        $result = $this->service->renewSignatureToken($signature, 30);

        $this->assertTrue($result);

        $signature->refresh();
        $this->assertTrue($signature->expires_at->isAfter($originalExpiration));
    }

    /** @test */
    public function maintenance_order_can_have_signatures()
    {
        $order = MaintenanceOrder::factory()->for($this->tenant)->create();

        $signerData = ['name' => 'Técnico Silva'];
        $link = $this->service->generateSignatureLink($order, $signerData);

        $this->assertStringContainsString('/assinatura/', $link);

        $this->assertDatabaseHas('document_signatures', [
            'signable_type' => MaintenanceOrder::class,
            'signable_id' => $order->id,
        ]);
    }
}
