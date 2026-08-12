<?php

namespace Tests\Feature\Chat;

use App\Livewire\GlobalChat;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatAppTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithUser(string $label): array
    {
        $plan = Plan::create([
            'name' => 'Plano Chat '.$label.' '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_chat'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant '.$label.' '.uniqid(), 'slug' => 'tenant-'.strtolower($label).'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Usuário '.$label, 'email' => strtolower($label).'-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-teste-123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$tenant, $user];
    }

    /**
     * Prova que o risco técnico identificado no plano (Tenancy::current()
     * funcionar fora do contexto de middleware do painel Filament) está
     * de fato mitigado: dois usuários de tenants diferentes, ambos
     * autenticados via /chat/login (não /admin/login), não se enxergam.
     */
    public function test_users_from_different_tenants_via_chat_login_do_not_see_each_other(): void
    {
        [$tenantA, $userA] = $this->makeTenantWithUser('A');
        [, $userB] = $this->makeTenantWithUser('B');

        $this->post(route('chat.login'), [
            'email' => $userA->email,
            'password' => 'senha-teste-123',
        ]);

        $this->assertAuthenticatedAs($userA);

        $contacts = Livewire::test(GlobalChat::class)->instance()->users();

        $this->assertFalse(
            $contacts->contains('id', $userB->id),
            'Usuário de outro tenant não deveria aparecer na lista de contatos do chat standalone.'
        );
    }

    public function test_same_tenant_colleague_appears_as_contact_via_chat_login(): void
    {
        [$tenant, $userA] = $this->makeTenantWithUser('A');

        $colleague = User::create([
            'name' => 'Colega A', 'email' => 'colega-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-teste-123'), 'tenant_id' => $tenant->id,
        ]);
        $colleague->forceFill(['email_verified_at' => now()])->save();

        $this->post(route('chat.login'), [
            'email' => $userA->email,
            'password' => 'senha-teste-123',
        ]);

        $contacts = Livewire::test(GlobalChat::class)->instance()->users();

        $this->assertTrue($contacts->contains('id', $colleague->id));
    }
}
