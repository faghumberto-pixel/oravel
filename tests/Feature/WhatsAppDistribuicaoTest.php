<?php

namespace Tests\Feature;

use App\Filament\Pages\CaixaWhatsApp;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantWhatsappSetting;
use App\Models\User;
use App\Models\WhatsappConversa;
use App\Models\WhatsappNumero;
use App\Services\DistribuicaoWhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppDistribuicaoTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private TenantWhatsappSetting $config;

    private WhatsappNumero $numeroEmpresa;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $this->tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->config = TenantWhatsappSetting::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'enabled' => true, 'access_token' => 'T', 'app_secret' => 'S', 'verify_token' => 'V']);
        $this->numeroEmpresa = WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'user_id' => null, 'phone_number_id' => 'N-EMP']);
    }

    private function usuario(string $nome, bool $admin = false): User
    {
        $u = User::create(['name' => $nome, 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $this->tenant->id]));
        }

        return $u;
    }

    private function conversa(string $telefone, ?User $responsavel = null): WhatsappConversa
    {
        return WhatsappConversa::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'numero_id' => $this->numeroEmpresa->id, 'telefone' => $telefone,
            'nome' => 'Cliente '.$telefone, 'responsavel_user_id' => $responsavel?->id, 'ultima_mensagem_em' => now(), 'ultima_recebida_em' => now(),
        ]);
    }

    public function test_fila_deixa_a_conversa_sem_dono(): void
    {
        $this->usuario('Ana');
        $c = $this->conversa('5519900000001');

        $this->assertNull(DistribuicaoWhatsApp::atribuir($c->load('numero'), $this->config));
        $this->assertNull($c->fresh()->responsavel_user_id);
    }

    public function test_rodizio_alterna_entre_os_atendentes_na_ordem(): void
    {
        $ana = $this->usuario('Ana');
        $bia = $this->usuario('Bia');
        $this->config->update(['distribuicao' => 'rodizio', 'atendentes' => [$ana->id, $bia->id]]);

        $donos = [];
        foreach (['5519900000011', '5519900000012', '5519900000013'] as $tel) {
            $c = $this->conversa($tel);
            DistribuicaoWhatsApp::atribuir($c->load('numero'), $this->config->fresh());
            $donos[] = $c->fresh()->responsavel_user_id;
        }

        $this->assertSame([$ana->id, $bia->id, $ana->id], $donos);
    }

    public function test_continuidade_vence_o_rodizio_responsavel_do_lead_e_quem_ja_atendeu(): void
    {
        $ana = $this->usuario('Ana');
        $bia = $this->usuario('Bia');
        $caio = $this->usuario('Caio');
        $this->config->update(['distribuicao' => 'rodizio', 'atendentes' => [$ana->id]]);

        // lead com responsável
        $lead = CrmLead::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Lead do Caio', 'assigned_user_id' => $caio->id, 'phone' => '(19) 90000-0021']);
        $c = $this->conversa('5519900000021');
        $c->update(['crm_lead_id' => $lead->id]);
        DistribuicaoWhatsApp::atribuir($c->fresh()->load(['numero', 'lead']), $this->config->fresh());
        $this->assertSame($caio->id, $c->fresh()->responsavel_user_id);

        // mesmo telefone que a Bia já atendeu, agora numa conversa nova
        $this->conversa('5519900000022', $bia);
        $outra = WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'user_id' => null, 'phone_number_id' => 'N-EMP2']);
        $nova = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'numero_id' => $outra->id, 'telefone' => '5519900000022']);
        DistribuicaoWhatsApp::atribuir($nova->load('numero'), $this->config->fresh());
        $this->assertSame($bia->id, $nova->fresh()->responsavel_user_id);
    }

    public function test_numero_de_usuario_nunca_entra_na_distribuicao(): void
    {
        $ana = $this->usuario('Ana');
        $this->config->update(['distribuicao' => 'rodizio', 'atendentes' => [$ana->id]]);
        $bruno = $this->usuario('Bruno');
        $nBruno = WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'user_id' => $bruno->id, 'phone_number_id' => 'N-B']);
        $c = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'numero_id' => $nBruno->id, 'telefone' => '5519900000031', 'responsavel_user_id' => $bruno->id]);

        $this->assertNull(DistribuicaoWhatsApp::atribuir($c->load('numero'), $this->config->fresh()));
        $this->assertSame($bruno->id, $c->fresh()->responsavel_user_id);
    }

    public function test_caixa_fila_assumir_transferir_e_devolver(): void
    {
        $admin = $this->usuario('Admin', admin: true);
        $ana = $this->usuario('Ana');
        $bia = $this->usuario('Bia');
        $intruso = $this->usuario('Intruso');
        $this->config->update(['atendentes' => [$ana->id, $bia->id]]);
        $fila = $this->conversa('5519900000041');

        // atendente vê a fila; quem não é atendente não vê
        $this->actingAs($intruso);
        Livewire::test(CaixaWhatsApp::class)->assertDontSee('Cliente 5519900000041');

        $this->actingAs($ana);
        $pagina = Livewire::test(CaixaWhatsApp::class)->assertSee('Cliente 5519900000041')->assertSee('Na fila');
        $pagina->call('assumir', $fila->id);
        $this->assertSame($ana->id, $fila->fresh()->responsavel_user_id);

        // a Bia não vê mais a conversa da Ana; o admin vê tudo
        $this->actingAs($bia);
        Livewire::test(CaixaWhatsApp::class)->assertDontSee('Cliente 5519900000041');
        $this->actingAs($admin);
        Livewire::test(CaixaWhatsApp::class)->set('verTodas', true)->assertSee('Cliente 5519900000041');

        // só o responsável (ou o admin) transfere
        $this->actingAs($bia);
        Livewire::test(CaixaWhatsApp::class)->call('transferir', $fila->id, $bia->id);
        $this->assertSame($ana->id, $fila->fresh()->responsavel_user_id);

        $this->actingAs($ana);
        Livewire::test(CaixaWhatsApp::class)->call('transferir', $fila->id, $bia->id);
        $this->assertSame($bia->id, $fila->fresh()->responsavel_user_id);
        $this->assertSame(1, $bia->notifications()->count());

        $this->actingAs($bia);
        Livewire::test(CaixaWhatsApp::class)->call('selecionar', $fila->id)->call('devolverFila', $fila->id);
        $this->assertNull($fila->fresh()->responsavel_user_id);
    }

    public function test_aviso_de_conversa_nova_na_fila_vai_para_os_atendentes(): void
    {
        $ana = $this->usuario('Ana');
        $this->usuario('Fora');
        $this->config->update(['atendentes' => [$ana->id]]);

        $json = json_encode(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => 'N-EMP'], 'contacts' => [['wa_id' => '5519900000051', 'profile' => ['name' => 'Novo Cliente']]],
            'messages' => [['from' => '5519900000051', 'id' => 'wamid.F1', 'type' => 'text', 'text' => ['body' => 'Preciso de um orçamento']]],
        ]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp-empresa/'.$this->tenant->id, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, 'S')], $json)->assertOk();

        $this->assertNull(WhatsappConversa::withoutGlobalScopes()->where('telefone', '5519900000051')->value('responsavel_user_id'));
        $this->assertSame(1, $ana->notifications()->count());
        $this->assertStringContainsString('na fila', mb_strtolower($ana->notifications()->first()->data['title']));
    }
}
