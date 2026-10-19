<?php

namespace Tests\Feature;

use App\Livewire\CrmLeadsMobile;
use App\Livewire\OsRapidaMobile;
use App\Models\Asset;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CampoTelasTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(string $pacote): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $tenant = Tenant::factory()->create(['plan_id' => $plano->id, 'campo_ativo' => true]);

        return TenantProvisioner::provision($tenant, ['name' => 'Maria Campo', 'email' => 'maria-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
    }

    private function lead(User $dono, array $extra = []): CrmLead
    {
        return CrmLead::create($extra + ['tenant_id' => $dono->tenant_id, 'name' => 'Lead '.uniqid(), 'company_name' => 'Empresa X', 'phone' => '(19) 99999-9999', 'stage' => 'novo', 'source' => 'site', 'assigned_user_id' => $dono->id]);
    }

    public function test_os_rapida_abre_a_os_com_o_equipamento_escolhido_e_segue_para_o_modo_campo(): void
    {
        $user = $this->admin('manutencao');
        $this->actingAs($user);
        $asset = Asset::factory()->create(['tenant_id' => $user->tenant_id]);
        $cliente = Client::factory()->create(['tenant_id' => $user->tenant_id]);

        $teste = Livewire::test(OsRapidaMobile::class)
            ->call('escolher', $asset->id)->set('clientId', $cliente->id)->set('local', 'Obra Centro')->set('descricao', 'Vazamento no cilindro')
            ->call('criar');

        $os = MaintenanceOrder::where('asset_id', $asset->id)->firstOrFail();
        $teste->assertRedirect(route('maintenance-orders.field-wizard', $os));
        $this->assertSame($user->id, $os->technician_id);
        $this->assertSame($cliente->id, $os->client_id);
        $this->assertSame('Aberto', $os->status);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $os->maintenance_type);
        $this->assertStringContainsString("Local: Obra Centro\nVazamento no cilindro", $os->description);
        $this->assertNotEmpty($os->os_number);
    }

    public function test_os_rapida_exige_equipamento_e_descricao_e_nao_aceita_equipamento_de_outra_empresa(): void
    {
        $user = $this->admin('manutencao');
        $outra = $this->admin('manutencao');
        $alheio = Asset::factory()->create(['tenant_id' => $outra->tenant_id]);
        $this->actingAs($user);

        Livewire::test(OsRapidaMobile::class)->call('criar')->assertHasErrors(['assetId', 'descricao']);

        Livewire::test(OsRapidaMobile::class)->set('assetId', $alheio->id)->set('descricao', 'Teste de isolamento')->call('criar')->assertHasErrors('assetId');
        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $alheio->id)->count());
    }

    public function test_campo_mostra_os_rapida_e_leads_conforme_o_produto(): void
    {
        $this->actingAs($this->admin('manutencao'))->get('/campo')->assertOk()->assertSee('OS rápida')->assertDontSee('Meus leads');
        $this->actingAs($this->admin('vendas'))->get('/campo')->assertOk()->assertSee('Meus leads')->assertDontSee('OS rápida');
    }

    public function test_registrar_contato_cria_a_interacao_e_atualiza_o_retorno_do_lead(): void
    {
        $user = $this->admin('vendas');
        $this->actingAs($user);
        $lead = $this->lead($user);
        $retorno = now()->addDays(3)->toDateString();

        Livewire::test(CrmLeadsMobile::class)
            ->call('abrir', $lead->id)->set('canal', 'whatsapp')->set('resumo', 'Pediu proposta')->set('proximoPasso', 'Enviar proposta')->set('proximoRetorno', $retorno)
            ->call('registrar')->assertHasNoErrors();

        $i = CrmLeadInteraction::where('crm_lead_id', $lead->id)->firstOrFail();
        $this->assertSame('whatsapp', $i->channel);
        $this->assertSame('Enviar proposta', $i->next_action);
        $this->assertSame($user->id, $i->user_id);
        $this->assertSame($retorno, $lead->fresh()->next_followup_date->toDateString());
        $this->assertNotNull($lead->fresh()->last_contacted_at);
    }

    public function test_registrar_contato_valida_resumo_e_data_passada(): void
    {
        $user = $this->admin('vendas');
        $this->actingAs($user);
        $lead = $this->lead($user);

        Livewire::test(CrmLeadsMobile::class)->call('abrir', $lead->id)->call('registrar')->assertHasErrors(['resumo']);
        Livewire::test(CrmLeadsMobile::class)->call('abrir', $lead->id)->set('resumo', 'Ligação feita')->set('proximoRetorno', now()->subDay()->toDateString())->call('registrar')->assertHasErrors(['proximoRetorno']);
        $this->assertSame(0, CrmLeadInteraction::where('crm_lead_id', $lead->id)->count());
    }

    public function test_lista_filtra_retornos_de_hoje_e_isola_empresas(): void
    {
        $user = $this->admin('vendas');
        $outra = $this->admin('vendas');
        $this->actingAs($user);
        $atrasado = $this->lead($user, ['name' => 'Retorno Vencido']);
        $atrasado->forceFill(['next_followup_date' => now()->subDay()->toDateString()])->saveQuietly();
        $this->lead($user, ['name' => 'Sem Retorno Algum']);
        $this->lead($outra, ['name' => 'Lead da Outra Empresa']);

        Livewire::test(CrmLeadsMobile::class)->assertSee('Retorno Vencido')->assertSee('Sem Retorno Algum')->assertDontSee('Lead da Outra Empresa')
            ->set('filtro', 'retornos')->assertSee('Retorno Vencido')->assertDontSee('Sem Retorno Algum')
            ->call('abrir', $atrasado->id)->assertSee('Registrar contato');
    }
}
