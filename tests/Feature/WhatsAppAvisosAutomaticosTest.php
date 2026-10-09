<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use App\Services\AvisosWhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppAvisosAutomaticosTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private TenantWhatsappSetting $config;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]], 200)]);
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['modulo_whatsapp' => true]]);
        $this->tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->config = TenantWhatsappSetting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'enabled' => true, 'access_token' => 'T', 'app_secret' => 'S', 'verify_token' => 'V',
            'aviso_cobranca' => true, 'aviso_cobranca_dias_antes' => 3, 'template_cobranca' => 'cobranca_aviso',
            'aviso_os' => true, 'template_os_concluida' => 'os_concluida',
        ]);
        WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'user_id' => null, 'phone_number_id' => 'N-EMP']);
    }

    private function cliente(bool $aceita = true, string $nome = 'Construtora Alfa'): Client
    {
        return Client::create(['tenant_id' => $this->tenant->id, 'name' => $nome.uniqid(), 'email' => uniqid().'@c.test', 'whatsapp' => '(19) 9'.random_int(1000, 9999).'-'.random_int(1000, 9999), 'aceita_avisos_whatsapp' => $aceita]);
    }

    private function conta(Client $cliente, int $diasParaVencer, string $status = 'pendente'): AccountReceivable
    {
        return AccountReceivable::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $cliente->id, 'description' => 'Locação outubro', 'amount' => 1500.5,
            'due_date' => now()->addDays($diasParaVencer)->toDateString(), 'status' => $status,
        ]);
    }

    public function test_lembra_o_vencimento_e_o_atraso_uma_unica_vez_so_para_quem_aceitou(): void
    {
        $aceita = $this->cliente(true);
        $recusa = $this->cliente(false);
        $this->conta($aceita, 3);                 // vence em 3 dias: lembrete
        $this->conta($aceita, -1, 'atrasado');    // venceu ontem: aviso de atraso
        $this->conta($aceita, 10);                // longe: nada
        $this->conta($aceita, 3, 'pago');         // já paga: nada
        $this->conta($recusa, 3);                 // não aceitou: nada

        $this->assertSame(2, AvisosWhatsApp::cobrancas($this->config));
        $this->assertSame(0, AvisosWhatsApp::cobrancas($this->config), 'segunda rodada não repete');

        $eventos = WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('evento')->sort()->values()->all();
        $this->assertSame(['cobranca_atrasada', 'cobranca_vencimento'], $eventos);

        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'cobranca_aviso'
            && $r['template']['components'][0]['parameters'][2]['text'] === 'R$ 1.500,50'
            && $r['template']['components'][0]['parameters'][1]['text'] === 'Locação outubro');
    }

    public function test_aviso_desligado_ou_sem_modelo_ou_sem_numero_da_empresa_nao_envia(): void
    {
        $c = $this->cliente(true);
        $this->conta($c, 3);

        $this->config->update(['aviso_cobranca' => false]);
        $this->assertSame(0, AvisosWhatsApp::cobrancas($this->config->fresh()));

        $this->config->update(['aviso_cobranca' => true, 'template_cobranca' => null]);
        $this->assertSame(0, AvisosWhatsApp::cobrancas($this->config->fresh()));

        $this->config->update(['template_cobranca' => 'cobranca_aviso']);
        WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();
        $this->assertSame(0, AvisosWhatsApp::cobrancas($this->config->fresh()));
        Http::assertNothingSent();
    }

    public function test_os_concluida_avisa_o_cliente_uma_vez(): void
    {
        $cliente = $this->cliente(true);
        $ordem = new MaintenanceOrder(['tenant_id' => $this->tenant->id, 'client_id' => $cliente->id, 'os_number' => 'OS-1234']);
        $ordem->id = (string) \Illuminate\Support\Str::uuid();
        $ordem->setRelation('client', $cliente);

        $this->assertTrue(AvisosWhatsApp::osConcluida($ordem));
        $this->assertFalse(AvisosWhatsApp::osConcluida($ordem), 'não repete');
        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'os_concluida' && $r['template']['components'][0]['parameters'][1]['text'] === 'OS-1234');

        $recusa = $this->cliente(false);
        $ordem2 = new MaintenanceOrder(['tenant_id' => $this->tenant->id, 'client_id' => $recusa->id, 'os_number' => 'OS-9']);
        $ordem2->id = (string) \Illuminate\Support\Str::uuid();
        $ordem2->setRelation('client', $recusa);
        $this->assertFalse(AvisosWhatsApp::osConcluida($ordem2));
    }
}
