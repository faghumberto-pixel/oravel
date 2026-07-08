<?php

namespace Tests\Feature;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Livewire\ChatWidget;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWidgetRenderHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_widget_appears_on_an_unrelated_admin_page_via_render_hook(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Hook '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_chat', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Hook '.uniqid(), 'slug' => 'tenant-hook-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $this->actingAs($admin);

        // Pagina QUALQUER do painel, nao relacionada a chat -- o widget deve
        // aparecer via render hook mesmo assim.
        $response = $this->get(SolicitacaoLocacaoResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSeeLivewire(ChatWidget::class);
    }
}
