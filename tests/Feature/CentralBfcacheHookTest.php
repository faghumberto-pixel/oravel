<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralBfcacheHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_dashboard_includes_bfcache_reload_script(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $this->actingAs($super);

        $response = $this->get('/central');

        if ($response->isRedirect()) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertOk();
        $response->assertSee('event.persisted', false);
    }
}
