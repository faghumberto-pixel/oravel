<?php

namespace Tests\Feature\Chat;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_manifest_is_served_and_points_to_chat_route(): void
    {
        $response = $this->get('/manifest-chat.json');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $manifest = json_decode($response->getContent(), true);

        $this->assertSame('/chat', $manifest['start_url']);
        $this->assertSame('/chat', $manifest['scope']);
        $this->assertSame('Oravel Chat', $manifest['name']);
    }

    public function test_chat_index_links_to_the_dedicated_manifest(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Chat '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_chat'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Chat '.uniqid(), 'slug' => 'tenant-chat-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Usuário Chat', 'email' => 'chat-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-teste-123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();
        $response->assertSee('manifest-chat.json', false);
    }

    public function test_app_index_does_not_reference_chat_manifest(): void
    {
        // O manifest do painel (public/manifest.json) não deve ganhar a tag
        // <link> do chat -- são PWAs separados de propósito.
        $response = $this->get('/admin/login');

        $response->assertDontSee('manifest-chat.json', false);
    }
}
