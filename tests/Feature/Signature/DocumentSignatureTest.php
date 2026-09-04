<?php

namespace Tests\Feature\Signature;

use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use Tests\TestCase;

class DocumentSignatureTest extends TestCase
{
    private Tenant $tenant;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->contract = Contract::factory()->for($this->tenant)->create();
    }

    /** @test */
    public function can_create_signature()
    {
        $signature = DocumentSignature::create([
            'tenant_id' => $this->tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $this->contract->id,
            'signer_name' => 'João Silva',
            'signer_document' => '123.456.789-00',
            'signer_email' => 'joao@example.com',
        ]);

        $this->assertNotNull($signature->token);
        $this->assertNotNull($signature->expires_at);
        $this->assertEquals('pending', $signature->status);
    }

    /** @test */
    public function signature_morphs_to_contract()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create();

        $this->assertInstanceOf(Contract::class, $signature->signable);
        $this->assertEquals($this->contract->id, $signature->signable->id);
    }

    /** @test */
    public function signature_morphs_to_maintenance_order()
    {
        $order = MaintenanceOrder::factory()->for($this->tenant)->create();

        $signature = DocumentSignature::factory()->for($order, 'signable')->create();

        $this->assertInstanceOf(MaintenanceOrder::class, $signature->signable);
        $this->assertEquals($order->id, $signature->signable->id);
    }

    /** @test */
    public function can_scope_by_pending()
    {
        DocumentSignature::factory(3)->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'signed',
        ]);

        $pending = DocumentSignature::pending()->count();

        $this->assertEquals(3, $pending);
    }

    /** @test */
    public function can_scope_by_signed()
    {
        DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        DocumentSignature::factory(2)->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'signed',
        ]);

        $signed = DocumentSignature::signed()->count();

        $this->assertEquals(2, $signed);
    }

    /** @test */
    public function can_scope_by_not_expired()
    {
        DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'expires_at' => now()->subDay(),
        ]);

        DocumentSignature::factory(2)->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'expires_at' => now()->addDay(),
        ]);

        $notExpired = DocumentSignature::notExpired()->count();

        $this->assertEquals(2, $notExpired);
    }

    /** @test */
    public function can_scope_by_token()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create();

        $found = DocumentSignature::byToken($signature->token)->first();

        $this->assertEquals($signature->id, $found->id);
    }

    /** @test */
    public function can_check_if_expired()
    {
        $expiredSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'expires_at' => now()->subDay(),
        ]);

        $validSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($expiredSignature->is_expired);
        $this->assertFalse($validSignature->is_expired);
    }

    /** @test */
    public function can_check_if_pending()
    {
        $pendingSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'pending',
        ]);

        $signedSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'signed',
        ]);

        $this->assertTrue($pendingSignature->is_pending);
        $this->assertFalse($signedSignature->is_pending);
    }

    /** @test */
    public function can_check_if_signed()
    {
        $pendingSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'pending',
        ]);

        $signedSignature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'signed',
        ]);

        $this->assertFalse($pendingSignature->is_signed);
        $this->assertTrue($signedSignature->is_signed);
    }

    /** @test */
    public function can_check_if_can_sign()
    {
        $canSign = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $expired = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $alreadySigned = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'signed',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($canSign->can_sign);
        $this->assertFalse($expired->can_sign);
        $this->assertFalse($alreadySigned->can_sign);
    }

    /** @test */
    public function can_mark_as_signed()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'status' => 'pending',
            'signed_at' => null,
        ]);

        $signature->markAsSigned();

        $this->assertEquals('signed', $signature->status);
        $this->assertNotNull($signature->signed_at);
    }

    /** @test */
    public function can_mark_as_expired()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create();

        $signature->markAsExpired();

        $this->assertEquals('expired', $signature->status);
    }

    /** @test */
    public function can_mark_as_canceled()
    {
        $signature = DocumentSignature::factory()->for($this->contract, 'signable')->create();

        $signature->markAsCanceled();

        $this->assertEquals('canceled', $signature->status);
    }

    /** @test */
    public function generates_random_token_on_creation()
    {
        $signature1 = DocumentSignature::factory()->for($this->contract, 'signable')->create();
        $signature2 = DocumentSignature::factory()->for($this->contract, 'signable')->create();

        $this->assertNotEquals($signature1->token, $signature2->token);
        $this->assertEquals(64, strlen($signature1->token));
    }

    /** @test */
    public function contract_has_signatures_trait()
    {
        $this->contract->signatures()->create([
            'tenant_id' => $this->tenant->id,
            'signable_type' => Contract::class,
            'signable_id' => $this->contract->id,
            'signer_name' => 'João Silva',
        ]);

        $this->assertEquals(1, $this->contract->signatures()->count());
    }

    /** @test */
    public function contract_has_pending_signatures_relationship()
    {
        DocumentSignature::factory(2)->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'signed',
        ]);

        $this->assertEquals(2, $this->contract->pendingSignatures()->count());
    }

    /** @test */
    public function contract_all_signatures_complete_check()
    {
        DocumentSignature::factory()->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'signed',
        ]);

        $this->assertTrue($this->contract->allSignaturesComplete());
    }

    /** @test */
    public function contract_count_pending_signatures()
    {
        DocumentSignature::factory(3)->for($this->contract, 'signable')->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertEquals(3, $this->contract->countPendingSignatures());
    }
}
