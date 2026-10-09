<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EmailMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Notifications\ClientMagicLinkNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class EmailsDoSistemaNaCaixaTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(): Client
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        return Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente', 'email' => 'cli'.uniqid().'@teste.com', 'password' => 'x12345678', 'portal_access_enabled_at' => now()]);
    }

    public function test_email_do_sistema_fica_registrado_como_enviado(): void
    {
        config(['mail.default' => 'array']);
        $cliente = $this->cliente();

        Mail::raw('Corpo do aviso', fn ($m) => $m->to($cliente->email)->subject('Assunto de teste'));

        $registro = EmailMessage::withoutGlobalScopes()->where('tenant_id', $cliente->tenant_id)->firstOrFail();
        $this->assertSame(EmailMessage::STATUS_ENVIADO, $registro->status);
        $this->assertSame('sistema', $registro->origem);
        $this->assertSame([$cliente->email], $registro->to_external);
        $this->assertSame('Assunto de teste', $registro->subject);
        $this->assertNotNull($registro->sent_at);
    }

    public function test_falha_no_servidor_fica_registrada_como_falhou(): void
    {
        $falha = new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new \RuntimeException('535 autenticação recusada');
            }

            public function __toString(): string
            {
                return 'falha';
            }
        };
        app(MailManager::class)->extend('falha', fn () => $falha);
        config(['mail.mailers.falha' => ['transport' => 'falha'], 'mail.default' => 'falha']);
        $cliente = $this->cliente();

        try {
            Mail::raw('x', fn ($m) => $m->to($cliente->email)->subject('Vai falhar'));
            $this->fail('deveria ter lançado erro');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('535', $e->getMessage());
        }

        $registro = EmailMessage::withoutGlobalScopes()->where('tenant_id', $cliente->tenant_id)->firstOrFail();
        $this->assertSame(EmailMessage::STATUS_FALHOU, $registro->status);
        $this->assertNull($registro->sent_at);
    }

    public function test_notificacao_por_email_registra_o_tipo(): void
    {
        config(['mail.default' => 'array']);
        $cliente = $this->cliente();

        $cliente->notify(new ClientMagicLinkNotification('https://exemplo.test/link'));

        $registro = EmailMessage::withoutGlobalScopes()->where('tenant_id', $cliente->tenant_id)->firstOrFail();
        $this->assertSame('ClientMagicLinkNotification', $registro->tipo);
        $this->assertSame(EmailMessage::STATUS_ENVIADO, $registro->status);
    }
}
