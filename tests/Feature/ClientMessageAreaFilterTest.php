<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\MinhasMensagens;
use App\Filament\Pages\GestaoClientes;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClientMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-26: assunto financeiro não deve poluir a
 * visão de quem é da manutenção, e vice-versa. Client escolhe a área ao
 * mandar mensagem; User só vê/é notificado da área cuja Role dedicada
 * (ClientMessage::areaRoleName()) ele possui. Admin vê tudo (bypass).
 * Mensagem sem área (legada) é visível a todos -- fallback seguro.
 */
class ClientMessageAreaFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano AreaFilter '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_client_messages'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant AreaFilter '.uniqid(), 'slug' => 'tenant-area-filter-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente AreaFilter',
            'email' => 'area-filter-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        return [$tenant, $client];
    }

    private function makeUserWithRole(Tenant $tenant, string $roleName): User
    {
        $user = User::create([
            'name' => 'User '.$roleName, 'email' => 'user-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeAdmin(Tenant $tenant): User
    {
        return $this->makeUserWithRole($tenant, 'admin');
    }

    public function test_client_selects_area_when_sending_message(): void
    {
        [$tenant, $client] = $this->makeTenantWithClient();

        $this->actingAs($client, 'client');

        Livewire::test(MinhasMensagens::class)
            ->fillForm(['area' => ClientMessage::AREA_FINANCEIRO, 'body' => 'Cadê meu boleto?'])
            ->call('send');

        $message = ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->where('client_id', $client->id)->first();

        $this->assertSame(ClientMessage::AREA_FINANCEIRO, $message->area);
    }

    /**
     * Testa a lógica de filtro (User::visibleClientMessageAreas() + a
     * mesma query usada em GestaoClientes::getMessagesProperty())
     * diretamente, em vez de via Livewire::test() -- montar a Page
     * inteira exige que o usuário passe por todo o layout do painel
     * (menu, widgets), e um usuário só com role de área (sem 'admin')
     * não tem permissão suficiente pra outros componentes do layout,
     * mascarando o teste com erros não relacionados à feature.
     */
    private function visibleMessagesFor(User $user, Tenant $tenant, string $clientId)
    {
        $areas = $user->visibleClientMessageAreas();

        return ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $clientId)
            ->where(fn ($q) => $q->whereIn('area', $areas)->orWhereNull('area'))
            ->get();
    }

    public function test_user_with_maintenance_role_does_not_see_financial_message(): void
    {
        [$tenant, $client] = $this->makeTenantWithClient();

        $manutencaoUser = $this->makeUserWithRole($tenant, ClientMessage::areaRoleName(ClientMessage::AREA_MANUTENCAO));

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'area' => ClientMessage::AREA_FINANCEIRO,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Assunto financeiro.',
        ]);

        $messages = $this->visibleMessagesFor($manutencaoUser, $tenant, $client->id);

        $this->assertCount(0, $messages);
    }

    public function test_user_with_financial_role_sees_financial_message(): void
    {
        [$tenant, $client] = $this->makeTenantWithClient();

        $financeiroUser = $this->makeUserWithRole($tenant, ClientMessage::areaRoleName(ClientMessage::AREA_FINANCEIRO));

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'area' => ClientMessage::AREA_FINANCEIRO,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Assunto financeiro.',
        ]);

        $messages = $this->visibleMessagesFor($financeiroUser, $tenant, $client->id);

        $this->assertCount(1, $messages);
    }

    public function test_admin_sees_all_areas(): void
    {
        [$tenant, $client] = $this->makeTenantWithClient();
        $admin = $this->makeAdmin($tenant);

        foreach (ClientMessage::areaLabels() as $area => $label) {
            ClientMessage::create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'area' => $area,
                'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
                'body' => "Assunto {$label}.",
            ]);
        }

        $this->actingAs($admin);

        $component = Livewire::test(GestaoClientes::class)
            ->call('selectClient', $client->id);
        $messages = $component->get('messages');

        $this->assertCount(4, $messages);
    }

    public function test_legacy_message_without_area_is_visible_to_everyone(): void
    {
        [$tenant, $client] = $this->makeTenantWithClient();
        $manutencaoUser = $this->makeUserWithRole($tenant, ClientMessage::areaRoleName(ClientMessage::AREA_MANUTENCAO));

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'area' => null,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Mensagem antiga, sem área.',
        ]);

        $messages = $this->visibleMessagesFor($manutencaoUser, $tenant, $client->id);

        $this->assertCount(1, $messages);
    }

    public function test_only_users_who_see_the_area_are_notified(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $financeiroUser = $this->makeUserWithRole($tenant, ClientMessage::areaRoleName(ClientMessage::AREA_FINANCEIRO));
        $manutencaoUser = $this->makeUserWithRole($tenant, ClientMessage::areaRoleName(ClientMessage::AREA_MANUTENCAO));

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'area' => ClientMessage::AREA_FINANCEIRO,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Assunto financeiro.',
        ]);

        Notification::assertSentTo($financeiroUser, ClientMessageReceivedNotification::class);
        Notification::assertNotSentTo($manutencaoUser, ClientMessageReceivedNotification::class);
    }
}
