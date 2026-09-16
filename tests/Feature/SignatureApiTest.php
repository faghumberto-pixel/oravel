<?php

namespace Tests\Feature;

use App\Models\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SignatureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_signature_creation_with_valid_data(): void
    {
        $payload = [
            'company' => 'Locadora ABC',
            'email' => 'legal@locadoraabc.com.br',
            'name' => 'João Silva',
            'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'hash' => 'abcd1234' . str_repeat('0', 56),
        ];

        $response = $this->postJson('/api/signatures', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'signature_id',
            'hash',
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('signatures', [
            'company' => 'Locadora ABC',
            'email' => 'legal@locadoraabc.com.br',
        ]);
    }

    public function test_signature_validation_missing_fields(): void
    {
        $payload = [
            'company' => 'Locadora ABC',
            // missing email, name, timestamp, hash
        ];

        $response = $this->postJson('/api/signatures', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    public function test_signature_validation_invalid_email(): void
    {
        $payload = [
            'company' => 'Locadora ABC',
            'email' => 'not-an-email',
            'name' => 'João Silva',
            'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'hash' => 'abcd1234' . str_repeat('0', 56),
        ];

        $response = $this->postJson('/api/signatures', $payload);

        $response->assertStatus(422);
    }

    public function test_signature_validation_invalid_hash_length(): void
    {
        $payload = [
            'company' => 'Locadora ABC',
            'email' => 'legal@locadoraabc.com.br',
            'name' => 'João Silva',
            'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'hash' => 'short-hash',
        ];

        $response = $this->postJson('/api/signatures', $payload);

        $response->assertStatus(422);
    }

    public function test_signature_unique_hash_constraint(): void
    {
        $payload = [
            'company' => 'Locadora ABC',
            'email' => 'legal@locadoraabc.com.br',
            'name' => 'João Silva',
            'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'hash' => 'abcd1234' . str_repeat('0', 56),
        ];

        $this->postJson('/api/signatures', $payload)->assertStatus(201);

        $response = $this->postJson('/api/signatures', $payload);

        $response->assertStatus(422);
    }

    public function test_signature_stores_ip_and_user_agent(): void
    {
        $payload = [
            'company' => 'Locadora ABC',
            'email' => 'legal@locadoraabc.com.br',
            'name' => 'João Silva',
            'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'hash' => 'abcd1234' . str_repeat('0', 56),
        ];

        $this->postJson('/api/signatures', $payload);

        $signature = Signature::latest()->first();
        $this->assertNotNull($signature->ip_origin);
        $this->assertNotNull($signature->user_agent);
    }
}
