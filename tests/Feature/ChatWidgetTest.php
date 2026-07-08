<?php

namespace Tests\Feature;

use App\Livewire\ChatWidget;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithTwoUsers(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Widget '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_chat'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Widget '.uniqid(), 'slug' => 'tenant-widget-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $colleague = User::create([
            'name' => 'Colega', 'email' => 'colega-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $colleague->forceFill(['email_verified_at' => now()])->save();
        $colleague->assignRole(Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin, $colleague];
    }

    public function test_widget_is_minimized_by_default(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();
        $this->actingAs($admin);

        Livewire::test(ChatWidget::class)
            ->assertSet('isExpanded', false)
            ->assertSet('selectedUserId', null);
    }

    public function test_toggle_expanded_flips_state(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();
        $this->actingAs($admin);

        Livewire::test(ChatWidget::class)
            ->call('toggleExpanded')
            ->assertSet('isExpanded', true)
            ->call('toggleExpanded')
            ->assertSet('isExpanded', false);
    }

    public function test_selecting_conversation_marks_it_read_and_loads_thread(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $room = ChatRoom::create(['tenant_id' => $tenant->id, 'type' => 'pessoal']);
        $room->users()->sync([$admin->id, $colleague->id]);
        ChatMessage::create([
            'tenant_id' => $tenant->id, 'chat_room_id' => $room->id, 'user_id' => $colleague->id,
            'message' => 'Oi admin',
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(ChatWidget::class)
            ->call('selectConversation', $colleague->id)
            ->assertSet('selectedUserId', $colleague->id);

        $this->assertCount(1, $component->instance()->chatMessages());
        $this->assertNotNull(ChatMessage::first()->read_at);
    }

    public function test_restore_state_reopens_conversation_after_reload(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();
        $this->actingAs($admin);

        // Simula o componente sendo remontado do zero numa pagina nova
        // (sem SPA, cada navegacao recria o componente) e o Alpine chamando
        // restoreState() com o que leu do sessionStorage.
        Livewire::test(ChatWidget::class)
            ->call('restoreState', $colleague->id, true)
            ->assertSet('isExpanded', true)
            ->assertSet('selectedUserId', $colleague->id);
    }

    public function test_restore_state_ignores_unknown_user_id(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();
        $this->actingAs($admin);

        Livewire::test(ChatWidget::class)
            ->call('restoreState', (string) Str::uuid(), true)
            ->assertSet('isExpanded', true)
            ->assertSet('selectedUserId', null);
    }

    public function test_total_unread_badge_reflects_unread_messages(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $room = ChatRoom::create(['tenant_id' => $tenant->id, 'type' => 'pessoal']);
        $room->users()->sync([$admin->id, $colleague->id]);
        ChatMessage::create(['tenant_id' => $tenant->id, 'chat_room_id' => $room->id, 'user_id' => $colleague->id, 'message' => 'Msg 1']);
        ChatMessage::create(['tenant_id' => $tenant->id, 'chat_room_id' => $room->id, 'user_id' => $colleague->id, 'message' => 'Msg 2']);

        $this->actingAs($admin);

        $component = Livewire::test(ChatWidget::class);
        $this->assertSame(2, $component->instance()->totalUnread());
    }

    public function test_sending_message_from_widget_persists_and_clears_input(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();
        $this->actingAs($admin);

        Livewire::test(ChatWidget::class)
            ->call('selectConversation', $colleague->id)
            ->set('newMessage', 'Oi colega, tudo bem?')
            ->call('sendMessage')
            ->assertSet('newMessage', '');

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $admin->id,
            'message' => 'Oi colega, tudo bem?',
        ]);
    }

    public function test_search_filters_contact_list(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();
        $this->actingAs($admin);

        $component = Livewire::test(ChatWidget::class)->set('search', 'zzz-inexistente');
        $this->assertCount(0, $component->instance()->users());

        $component2 = Livewire::test(ChatWidget::class)->set('search', 'Cole');
        $this->assertCount(1, $component2->instance()->users());
    }

    public function test_widget_view_renders_nothing_meaningful_when_plan_lacks_chat_module(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Sem Chat '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Sem Chat '.uniqid(), 'slug' => 'tenant-sem-chat-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $this->actingAs($admin);

        Livewire::test(ChatWidget::class)
            ->assertOk()
            ->assertDontSee('Mensagens');
    }
}
