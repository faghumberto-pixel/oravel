<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelCobrancas;
use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantAsaasSetting;
use App\Models\TenantWhatsappSetting;
use App\Models\User;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use App\Services\AvisosWhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 8C: link de pagamento no aviso de WhatsApp e painel de cobranças. */
class CobrancaWhatsAppEPainelTest extends TestCase
{
    use DatabaseTransactions;

    private const SANDBOX = 'https://api-sandbox.asaas.com/v3';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]], 200),
            self::SANDBOX.'/payments' => fn () => Http::response(['id' => 'pay_'.uniqid('', true), 'status' => 'PENDING', 'invoiceUrl' => 'https://pague.aqui/novo', 'bankSlipUrl' => 'https://b'], 200),
        ]);

        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_whatsapp' => true, TenantAsaasSetting::FEATURE => true, 'tabela_account_receivables' => true]]);
        $this->tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        TenantWhatsappSetting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'enabled' => true, 'access_token' => 'T', 'app_secret' => 'S', 'verify_token' => 'V',
            'aviso_cobranca' => true, 'aviso_cobranca_dias_antes' => 3, 'template_cobranca' => 'cobranca_link', 'cobranca_com_link' => true,
        ]);
        WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'user_id' => null, 'phone_number_id' => 'N-EMP']);
    }

    private function asaas(): TenantAsaasSetting
    {
        return TenantAsaasSetting::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'enabled' => true, 'api_key' => 'KEY']);
    }

    private function cliente(bool $aceita = true, ?string $zap = '(19) 99999-1234'): Client
    {
        return Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente '.uniqid(), 'cpf_cnpj' => '11.222.333/0001-81', 'asaas_customer_id' => 'cus_1', 'whatsapp' => $zap, 'aceita_avisos_whatsapp' => $aceita]);
    }

    private function conta(array $extra = [], ?Client $cliente = null): AccountReceivable
    {
        return AccountReceivable::create($extra + ['tenant_id' => $this->tenant->id, 'client_id' => ($cliente ?? $this->cliente())->id, 'description' => 'Locação out', 'amount' => 1500.5,
            'due_date' => now()->addDays(3)->toDateString(), 'status' => 'pendente']);
    }

    private function enviar(): int
    {
        return AvisosWhatsApp::cobrancas(TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first());
    }

    public function test_migration_adds_the_flag_and_is_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('tenant_whatsapp_settings', 'cobranca_com_link'));
        $m = require base_path('database/migrations/2026_11_25_100000_add_cobranca_com_link_to_whatsapp_settings.php');
        $m->down();
        $this->assertFalse(Schema::hasColumn('tenant_whatsapp_settings', 'cobranca_com_link'));
        $m->up();
    }

    public function test_notice_carries_the_payment_link_as_the_fifth_variable(): void
    {
        $this->conta(['asaas_payment_id' => 'pay_x', 'asaas_invoice_url' => 'https://pague.aqui/abc']);

        $this->assertSame(1, $this->enviar());

        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'cobranca_link'
            && count($r['template']['components'][0]['parameters']) === 5
            && $r['template']['components'][0]['parameters'][4]['text'] === 'https://pague.aqui/abc');
    }

    public function test_without_a_link_the_charge_is_generated_first_when_automatic_billing_is_ready(): void
    {
        $this->asaas();
        $conta = $this->conta();

        $this->assertSame(1, $this->enviar());

        $this->assertNotNull($conta->fresh()->asaas_payment_id);
        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'cobranca_link' && $r['template']['components'][0]['parameters'][4]['text'] === 'https://pague.aqui/novo');
    }

    public function test_without_a_link_and_without_billing_nothing_is_sent_and_the_old_format_still_works(): void
    {
        $this->conta();
        $this->assertSame(0, $this->enviar(), 'modelo com link e sem link de pagamento: ainda não avisa');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com'));

        TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['cobranca_com_link' => false]);
        $this->assertSame(1, $this->enviar());
        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'cobranca_link' && count($r['template']['components'][0]['parameters']) === 4);
    }

    public function test_manual_resend_ignores_the_already_sent_rule_and_explains_why_it_cannot_send(): void
    {
        $conta = $this->conta(['asaas_payment_id' => 'pay_x', 'asaas_invoice_url' => 'https://pague.aqui/abc']);

        $this->assertSame(1, $this->enviar());
        $this->assertSame(0, $this->enviar(), 'rotina diária não repete');
        AvisosWhatsApp::reenviarCobranca($conta);
        $this->assertSame(2, WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('related_id', $conta->id)->count());

        $casos = [
            'não aceitou' => [$this->conta([], $this->cliente(false)), 'não aceitou'],
            'sem WhatsApp' => [$this->conta([], $this->cliente(true, null)), 'WhatsApp válido'],
            'sem link' => [$this->conta(), 'link de pagamento'],
        ];

        foreach ($casos as $nome => [$c, $trecho]) {
            try {
                AvisosWhatsApp::reenviarCobranca($c);
                $this->fail("{$nome}: deveria recusar");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }
    }

    // ---------------------------------------------------------------- painel

    private function admin(): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));

        return $u;
    }

    public function test_panel_summarizes_filters_and_runs_the_row_actions(): void
    {
        $this->asaas();
        $this->actingAs($this->admin());
        $aVencer = $this->conta(['amount' => 100]);
        $vencida = $this->conta(['amount' => 250, 'due_date' => now()->subDays(4)->toDateString(), 'status' => 'atrasado']);
        $recebida = $this->conta(['amount' => 900, 'status' => 'pago', 'payment_date' => now()->toDateString()]);
        $falha = $this->conta(['amount' => 70, 'cobranca_erro' => 'Cliente sem CPF']);
        $outra = Tenant::create(['name' => 'Outra', 'slug' => 'o-'.uniqid(), 'plan_id' => $this->tenant->plan_id, 'status' => 'active']);
        AccountReceivable::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'description' => 'De outra empresa', 'amount' => 99999, 'due_date' => now()->addDay(), 'status' => 'pendente']);

        $painel = Livewire::test(PainelCobrancas::class)->assertSuccessful();
        $resumo = $painel->instance()->resumo();

        $this->assertEquals(170, $resumo['a_vencer']['total']);   // 100 + 70
        $this->assertEquals(250, $resumo['vencidas']['total']);
        $this->assertEquals(900, $resumo['recebidas']['total']);
        $this->assertSame(1, $resumo['falhas']['qtd']);
        $painel->assertCanSeeTableRecords([$aVencer, $vencida, $recebida, $falha])->assertCountTableRecords(4);

        $painel->filterTable('situacao', 'vencidas');
        $painel->assertCanSeeTableRecords([$vencida])->assertCanNotSeeTableRecords([$aVencer, $recebida])
            ->filterTable('situacao', 'falhas')->assertCanSeeTableRecords([$falha])->assertCanNotSeeTableRecords([$aVencer]);

        Livewire::test(PainelCobrancas::class)->callTableAction('gerar', $aVencer);
        $this->assertNotNull($aVencer->fresh()->asaas_payment_id);

        Livewire::test(PainelCobrancas::class)->assertTableActionHidden('gerar', $aVencer->fresh())->assertTableActionVisible('link', $aVencer->fresh())
            ->assertTableActionHidden('avisar', $recebida)->callTableAction('avisar', $aVencer->fresh());
        $this->assertSame(1, WhatsappMensagem::withoutGlobalScopes()->where('related_id', $aVencer->id)->count());
    }

    public function test_panel_is_gated_by_the_complement(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(PainelCobrancas::canAccess());

        $sem = Plan::create(['name' => 'S'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_account_receivables' => true]]);
        $this->tenant->update(['plan_id' => $sem->id]);
        $this->assertFalse(PainelCobrancas::canAccess());
    }
}
