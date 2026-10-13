<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\FaleConosco;
use App\Filament\Resources\ClientFeedbackResource\Pages\ManageClientFeedbacks;
use App\Models\Client;
use App\Models\ClientFeedback;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Portal do Cliente: reclamação, sugestão e elogio (tela Fale Conosco) e a resposta da equipe em Voz do Cliente. */
class ClientePortalFaleConoscoTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private Client $cliente;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'Plano FC '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_client_feedbacks' => true, 'tabela_clients' => true]]);
        $this->tenant = Tenant::create(['name' => 'Locadora FC', 'slug' => 'fc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->cliente = $this->novoCliente($this->tenant, 'Construtora Alfa');
        $this->admin = User::create(['name' => 'Chefe', 'email' => 'fc-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
    }

    private function novoCliente(Tenant $t, string $nome): Client
    {
        $c = Client::create(['tenant_id' => $t->id, 'name' => $nome, 'email' => 'c-'.uniqid().'@t.com', 'password' => 'senha123']);
        $c->forceFill(['portal_access_enabled_at' => now()])->save();

        return $c;
    }

    public function test_menu_do_portal_tem_fale_conosco(): void
    {
        $this->actingAs($this->cliente, 'client')->get('/cliente/fale-conosco')->assertOk()->assertSee('Fale Conosco')->assertSee('Reclamação, Sugestão ou Elogio');
    }

    public function test_cliente_registra_tipo_assunto_e_descricao_e_recebe_protocolo(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal-cliente'));

        Livewire::actingAs($this->cliente, 'client')->test(FaleConosco::class)
            ->fillForm(['type' => 'elogio', 'subject' => 'Atendimento do técnico', 'description' => 'O técnico foi muito atencioso.'])
            ->call('send')
            ->assertHasNoFormErrors();

        $f = ClientFeedback::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([$this->tenant->id, $this->cliente->id, 'elogio', 'recebido'], [$f->tenant_id, $f->client_id, $f->type, $f->status]);
        $this->assertMatchesRegularExpression('/^FB-\d{6}-[A-Z0-9]{4}$/', $f->protocol);
    }

    public function test_campos_obrigatorios_e_tipo_invalido(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal-cliente'));

        Livewire::actingAs($this->cliente, 'client')->test(FaleConosco::class)
            ->fillForm(['type' => 'xingamento', 'subject' => '', 'description' => ''])
            ->call('send')
            ->assertHasFormErrors(['type', 'subject', 'description']);

        $this->assertSame(0, ClientFeedback::withoutGlobalScopes()->count());
    }

    public function test_equipe_e_avisada_e_cliente_so_ve_os_proprios_registros(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal-cliente'));
        $outro = $this->novoCliente($this->tenant, 'Outra Empresa');
        $dele = ClientFeedback::create(['tenant_id' => $this->tenant->id, 'client_id' => $outro->id, 'type' => 'reclamacao', 'subject' => 'Assunto do outro', 'description' => 'x']);

        Livewire::actingAs($this->cliente, 'client')->test(FaleConosco::class)
            ->fillForm(['type' => 'reclamacao', 'subject' => 'Entrega atrasada', 'description' => 'A entrega atrasou dois dias.'])
            ->call('send');

        $meu = ClientFeedback::withoutGlobalScopes()->where('client_id', $this->cliente->id)->firstOrFail();

        Livewire::actingAs($this->cliente, 'client')->test(FaleConosco::class)
            ->assertCanSeeTableRecords([$meu])
            ->assertCanNotSeeTableRecords([$dele]);

        // 1 aviso do registro do outro cliente + 1 deste: o administrador da empresa recebe cada um no sino.
        $this->assertSame(2, $this->admin->notifications()->count());
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), $meu->protocol)));
    }

    public function test_equipe_responde_e_o_cliente_le_a_resposta(): void
    {
        $f = ClientFeedback::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id, 'type' => 'reclamacao', 'subject' => 'Entrega atrasada', 'description' => 'Atrasou.']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->admin)->test(ManageClientFeedbacks::class)
            ->assertSuccessful()
            ->callTableAction('responder', $f, data: ['status' => 'concluido', 'response' => 'Pedimos desculpas, já corrigimos a rota.'])
            ->assertHasNoTableActionErrors();

        $f->refresh();
        $this->assertSame(['concluido', $this->admin->id], [$f->status, $f->responded_by]);
        $this->assertNotNull($f->responded_at);

        $this->actingAs($this->cliente, 'client');
        Filament::setCurrentPanel(Filament::getPanel('portal-cliente'));
        Livewire::actingAs($this->cliente, 'client')->test(FaleConosco::class)
            ->mountTableAction('resposta', $f)
            ->assertSee('Pedimos desculpas, já corrigimos a rota.');
    }
}
