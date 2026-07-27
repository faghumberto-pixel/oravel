<?php

namespace Tests\Feature;

use App\Mail\GenericPdfMail;
use App\Models\EmailMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailMessageTest extends TestCase
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

        $userA = User::create([
            'name' => 'Usuário A', 'email' => 'a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $userA->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $userB = User::create([
            'name' => 'Usuário B', 'email' => 'b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $userB->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        return [$tenant, $userA, $userB];
    }

    public function test_send_fails_without_subject(): void
    {
        [$tenant, $userA] = $this->makeTenantWithTwoUsers();

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'to_external' => ['cliente@exemplo.com.br'],
        ]);

        $this->expectException(\RuntimeException::class);
        $message->send();
    }

    public function test_send_fails_without_any_recipient(): void
    {
        [$tenant, $userA] = $this->makeTenantWithTwoUsers();

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Sem destinatário',
        ]);

        $this->expectException(\RuntimeException::class);
        $message->send();
    }

    public function test_send_to_internal_recipient_does_not_call_smtp(): void
    {
        Mail::fake();

        [$tenant, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Aviso interno',
            'body' => 'Mensagem só entre a equipe.',
        ]);
        $message->recipients()->attach($userB->id);

        $message->send();

        Mail::assertNothingSent();
        $this->assertSame(EmailMessage::STATUS_ENVIADO, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->sent_at);
    }

    public function test_send_to_external_recipient_sends_real_mail_with_attachment(): void
    {
        Mail::fake();

        [$tenant, $userA] = $this->makeTenantWithTwoUsers();

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Proposta comercial',
            'body' => 'Segue em anexo.',
            'to_external' => ['cliente@exemplo.com.br'],
        ]);
        $message->addMedia(UploadedFile::fake()->create('proposta.pdf', 10, 'application/pdf'))
            ->toMediaCollection('anexos');

        $message->send();

        $this->assertSame(EmailMessage::STATUS_ENVIADO, $message->fresh()->status);

        Mail::assertSent(GenericPdfMail::class, function (GenericPdfMail $mail) {
            return $mail->hasTo('cliente@exemplo.com.br')
                && $mail->subjectLine === 'Proposta comercial'
                && count($mail->extraAttachments) === 1
                && $mail->extraAttachments[0]['filename'] === 'proposta.pdf';
        });
    }

    public function test_send_marks_as_failed_when_smtp_throws(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \Exception('Falha simulada de SMTP'));

        [$tenant, $userA] = $this->makeTenantWithTwoUsers();

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Vai falhar',
            'to_external' => ['cliente@exemplo.com.br'],
        ]);

        try {
            $message->send();
            $this->fail('Esperava uma exceção de envio.');
        } catch (\Throwable $e) {
            // esperado
        }

        $message->refresh();
        $this->assertSame(EmailMessage::STATUS_FALHOU, $message->status);
        $this->assertNotNull($message->error);
    }

    public function test_mark_read_for_sets_read_at_only_once(): void
    {
        [$tenant, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $message = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Leia isso',
            'status' => EmailMessage::STATUS_ENVIADO,
        ]);
        $message->recipients()->attach($userB->id);

        $this->assertNull($message->recipients()->first()->pivot->read_at);

        $message->markReadFor($userB);
        $firstReadAt = $message->recipients()->first()->pivot->read_at;
        $this->assertNotNull($firstReadAt);

        // Chamar de novo não deve sobrescrever o horário da primeira leitura.
        $this->travel(1)->hours();
        $message->markReadFor($userB);
        $this->assertSame((string) $firstReadAt, (string) $message->recipients()->first()->pivot->read_at);
    }
}
