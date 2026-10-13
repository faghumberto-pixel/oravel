<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** A busca de telas (Ctrl+K) existe no topo da Central, como no painel admin. */
class CentralBuscaDeTelasTest extends TestCase
{
    use DatabaseTransactions;

    public function test_topo_da_central_tem_a_busca_de_telas(): void
    {
        $email = 'busca-central-'.uniqid().'@oravel.test';
        config(['oravel.super_admins' => [$email]]);
        $u = User::create(['name' => 'Super', 'email' => $email, 'password' => bcrypt('x')]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $html = $this->actingAs($u)->get('/central')->assertOk()->getContent();

        $this->assertStringContainsString('Buscar tela do sistema', $html);
    }
}
