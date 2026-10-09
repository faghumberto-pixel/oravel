<?php

namespace Tests\Feature;

use App\Filament\Pages\EmailDaEmpresa;
use App\Mail\TenantAwareTransport;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class EmailDaEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $nome): array
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => $nome, 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $cliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.$nome, 'email' => 'c'.uniqid().'@cliente.test']);

        return [$tenant, $cliente];
    }

    private function caixa(Tenant $tenant, bool $ativa = true): TenantMailSetting
    {
        return TenantMailSetting::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'enabled' => $ativa, 'host' => 'smtp.locadora.test', 'port' => 465, 'security' => 'ssl',
            'username' => 'envio@locadora.test', 'password' => 'segredo123', 'from_address' => 'comercial@locadora.test', 'from_name' => 'Locadora Alfa',
        ]);
    }

    /** Transporte falso que só guarda o que recebeu. */
    private function falso(): TransportInterface
    {
        return new class implements TransportInterface
        {
            public ?RawMessage $recebida = null;

            public ?Envelope $envelope = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->recebida = $message;
                $this->envelope = $envelope;

                return null;
            }

            public function __toString(): string
            {
                return 'falso';
            }
        };
    }

    private function email(string $para): Email
    {
        return (new Email)->from('contato@oravel.com.br')->to($para)->subject('Oi')->text('corpo');
    }

    public function test_empresa_com_caixa_ativa_envia_por_ela_com_o_endereco_dela(): void
    {
        [$tenant, $cliente] = $this->empresa('Locadora Alfa');
        $this->caixa($tenant);
        $plataforma = $this->falso();
        $daEmpresa = $this->falso();
        $transporte = new TenantAwareTransport($plataforma, fn () => $daEmpresa);

        $transporte->send($this->email($cliente->email));

        $this->assertNull($plataforma->recebida, 'nao pode usar a caixa da Oravel');
        $this->assertNotNull($daEmpresa->recebida);
        $this->assertSame('comercial@locadora.test', $daEmpresa->recebida->getFrom()[0]->getAddress());
        $this->assertSame('Locadora Alfa', $daEmpresa->recebida->getFrom()[0]->getName());
        $this->assertSame('comercial@locadora.test', $daEmpresa->envelope->getSender()->getAddress());
        $this->assertSame($cliente->email, $daEmpresa->envelope->getRecipients()[0]->getAddress());
    }

    public function test_sem_caixa_ou_desativada_usa_a_da_oravel_e_nao_mistura_empresas(): void
    {
        [$comCaixa, ] = $this->empresa('Com Caixa');
        [$semCaixa, $clienteSem] = $this->empresa('Sem Caixa');
        [$desativada, $clienteDes] = $this->empresa('Desativada');
        $this->caixa($comCaixa);
        $this->caixa($desativada, ativa: false);

        foreach ([$clienteSem, $clienteDes] as $cliente) {
            $plataforma = $this->falso();
            $daEmpresa = $this->falso();

            (new TenantAwareTransport($plataforma, fn () => $daEmpresa))->send($this->email($cliente->email));

            $this->assertNotNull($plataforma->recebida);
            $this->assertNull($daEmpresa->recebida);
            $this->assertSame('contato@oravel.com.br', $plataforma->recebida->getFrom()[0]->getAddress());
        }
    }

    public function test_destinatario_fora_do_sistema_usa_a_caixa_da_oravel(): void
    {
        $plataforma = $this->falso();

        (new TenantAwareTransport($plataforma, fn () => $this->falso()))->send($this->email('alguem@desconhecido.test'));

        $this->assertNotNull($plataforma->recebida);
    }

    public function test_tela_salva_senha_criptografada_e_nao_ativa_se_o_servidor_recusar(): void
    {
        [$tenant] = $this->empresa('Locadora Beta');
        $admin = User::create(['name' => 'Admin', 'email' => 'a'.uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));
        $this->actingAs($admin);

        Livewire::test(EmailDaEmpresa::class)
            ->fillForm(['host' => '127.0.0.1', 'port' => 1, 'security' => 'ssl', 'username' => 'u@x.test', 'password' => 'minhaSenha!9', 'from_address' => 'envio@x.test', 'from_name' => 'Beta'])
            ->call('testarEAtivar')
            ->assertNotified();

        $config = TenantMailSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertFalse($config->enabled, 'servidor recusou: nao pode ativar');
        $this->assertFalse($config->last_test_ok);
        $this->assertSame('minhaSenha!9', $config->password);
        $this->assertStringNotContainsString('minhaSenha!9', (string) DB::table('tenant_mail_settings')->where('id', $config->id)->value('password'));
    }
}
