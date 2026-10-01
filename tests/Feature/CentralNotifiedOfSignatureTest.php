<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A Central (super admins) recebe aviso no sino quando um cliente assina o contrato. */
class CentralNotifiedOfSignatureTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function makeUser(string $email, ?string $tenantId = null): User
    {
        $user = User::create(['name' => 'U '.$email, 'email' => $email, 'password' => bcrypt('teste123'), 'tenant_id' => $tenantId]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        return $user;
    }

    public function test_signing_a_tenant_contract_notifies_super_admins_only(): void
    {
        Storage::fake();
        $super = $this->makeUser('super-'.uniqid().'@oravel.com.br');
        config(['oravel.super_admins' => [strtoupper($super->email)]]); // caixa diferente de propósito

        $plan = Plan::create([
            'name' => 'Plano Aviso', 'price' => 600, 'base_price' => 600, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenant = Tenant::create(['name' => 'Topmixx Teste', 'slug' => 'topmixx-teste-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial']);
        $outro = $this->makeUser('outro-'.uniqid().'@cliente.com', $tenant->id);

        $link = app(SignatureService::class)->generateSignatureLink($tenant, ['name' => 'Marivan', 'email' => 'm@x.com']);
        $token = basename($link);

        app(SignatureService::class)->signDocument($token, ['signature_base64' => self::PNG, 'ip_address' => '1.2.3.4']);

        $notifications = $super->refresh()->notifications;
        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('Contrato assinado: Topmixx Teste', $notifications->first()->data['title']);
        $this->assertStringContainsString('Marivan assinou', $notifications->first()->data['body']);
        $this->assertSame(0, $outro->refresh()->notifications->count());
    }
}
