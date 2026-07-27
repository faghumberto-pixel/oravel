<?php

namespace Tests\Feature;

use App\Filament\Pages\CaixaDeEmail;
use App\Models\EmailMessage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewInternalEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CaixaDeEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User, 2: User}
     */
    private function makeTenantWithTwoUsers(): array
    {
        $plan = Plan::create([
            'name' => 'Plano E-mail '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['caixa_email'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant E-mail '.uniqid(), 'slug' => 'tenant-email-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        // 'admin' bypassa o check de permissao individual (AbstractPolicy) --
        // sem isso, canAccess() da Page nega com 403 mesmo com a feature do
        // plano habilitada (mesma trava que a permissao granular ler_email_message
        // resolveria, mas essa so' e' criada quando "Perfis de Acesso" e' aberto).
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $userA = User::create([
            'name' => 'Usuário A', 'email' => 'a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $userA->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $userA->assignRole($role);

        $userB = User::create([
            'name' => 'Usuário B', 'email' => 'b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $userB->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $userB->assignRole($role);

        return [$tenant, $userA, $userB];
    }

    public function test_compose_save_and_send_internal_email_notifies_recipient(): void
    {
        Notification::fake();

        [, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $this->actingAs($userA);

        Livewire::test(CaixaDeEmail::class)
            ->call('newDraft')
            ->set('composeData.to_user_ids', [$userB->id])
            ->set('composeData.subject', 'Peça chegou')
            ->set('composeData.body', 'Pode vir buscar.')
            ->call('sendMessage')
            ->assertSet('folder', 'enviados');

        $message = EmailMessage::sole();
        $this->assertSame(EmailMessage::STATUS_ENVIADO, $message->status);
        $this->assertTrue($message->recipients->contains('id', $userB->id));

        Notification::assertSentTo($userB, NewInternalEmailNotification::class);
        Notification::assertNotSentTo($userA, NewInternalEmailNotification::class);
    }

    public function test_draft_can_be_saved_and_resumed(): void
    {
        [, $userA] = $this->makeTenantWithTwoUsers();

        $this->actingAs($userA);

        $component = Livewire::test(CaixaDeEmail::class)
            ->call('newDraft')
            ->set('composeData.subject', 'Rascunho')
            ->set('composeData.body', 'Ainda escrevendo...')
            ->call('saveDraft');

        $message = EmailMessage::sole();
        $this->assertSame(EmailMessage::STATUS_RASCUNHO, $message->status);
        $this->assertSame('Rascunho', $message->subject);

        // Reabrir num componente novo (simula reload de página) e confirmar
        // que o conteúdo salvo volta pro formulário.
        Livewire::test(CaixaDeEmail::class)
            ->call('selectMessage', $message->id)
            ->assertSet('isComposing', true)
            ->assertSet('composeData.subject', 'Rascunho')
            ->assertSet('composeData.body', 'Ainda escrevendo...');
    }

    public function test_user_cannot_see_another_users_draft(): void
    {
        [$tenant, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $draft = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Rascunho privado de A',
            'status' => EmailMessage::STATUS_RASCUNHO,
        ]);

        $this->actingAs($userB);

        Livewire::test(CaixaDeEmail::class)
            ->call('selectMessage', $draft->id)
            ->assertSet('activeMessageId', null);
    }

    public function test_user_cannot_see_sent_email_they_are_not_part_of(): void
    {
        [$tenant, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $userC = User::create([
            'name' => 'Usuário C', 'email' => 'c-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $userC->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $userC->assignRole(Role::where('tenant_id', $tenant->id)->where('name', 'admin')->first());

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Só entre A e B',
            'status' => EmailMessage::STATUS_ENVIADO,
            'sent_at' => now(),
        ]);
        $message->recipients()->attach($userB->id);

        $this->actingAs($userC);

        Livewire::test(CaixaDeEmail::class)
            ->call('selectMessage', $message->id)
            ->assertSet('activeMessageId', null);
    }

    public function test_folder_scoping_shows_correct_messages_per_user(): void
    {
        [$tenant, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $sent = EmailMessage::create([
            'tenant_id' => $tenant->id, 'from_user_id' => $userA->id,
            'subject' => 'Enviado por A', 'status' => EmailMessage::STATUS_ENVIADO, 'sent_at' => now(),
        ]);
        $sent->recipients()->attach($userB->id);

        EmailMessage::create([
            'tenant_id' => $tenant->id, 'from_user_id' => $userA->id,
            'subject' => 'Rascunho de A', 'status' => EmailMessage::STATUS_RASCUNHO,
        ]);

        $this->actingAs($userA);
        $enviadosDeA = Livewire::test(CaixaDeEmail::class)->call('setFolder', 'enviados')->instance()->getMessagesQuery()->get();
        $this->assertCount(1, $enviadosDeA);
        $this->assertSame('Enviado por A', $enviadosDeA->first()->subject);

        $rascunhosDeA = Livewire::test(CaixaDeEmail::class)->call('setFolder', 'rascunhos')->instance()->getMessagesQuery()->get();
        $this->assertCount(1, $rascunhosDeA);

        $this->actingAs($userB);
        $recebidosDeB = Livewire::test(CaixaDeEmail::class)->call('setFolder', 'recebidos')->instance()->getMessagesQuery()->get();
        $this->assertCount(1, $recebidosDeB);
        $this->assertSame('Enviado por A', $recebidosDeB->first()->subject);

        $rascunhosDeB = Livewire::test(CaixaDeEmail::class)->call('setFolder', 'rascunhos')->instance()->getMessagesQuery()->get();
        $this->assertCount(0, $rascunhosDeB);
    }
}
