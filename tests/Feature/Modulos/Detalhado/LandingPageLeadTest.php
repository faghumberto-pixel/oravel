<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Central\Resources\LandingPageLeadResource;
use App\Models\LandingPageLead;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** Leads da Landing Page: modulo da Central (so super admin), sem tenant. */
class LandingPageLeadTest extends PerfisTestCase
{
    private function superAdmin(): User
    {
        config(['oravel.super_admins' => ['super@oravel.com.br']]);
        $user = User::create([
            'name' => 'Super', 'email' => 'super@oravel.com.br', 'password' => bcrypt('teste123'), 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function lead(array $extra = []): LandingPageLead
    {
        return $this->criar(LandingPageLead::class, $extra + [
            'name' => 'Carlos Lead', 'email' => 'carlos@exemplo.com.br', 'phone' => '(19) 99999-0000',
            'company' => 'Locadora X', 'segment' => 'frota', 'product' => 'FROTA', 'status' => 'novo',
        ]);
    }

    public function test_so_super_admin_acessa_pela_policy(): void
    {
        $lead = $this->lead();
        $super = $this->superAdmin();

        $this->actingAs($super);
        $this->assertTrue($super->can('viewAny', LandingPageLead::class));
        $this->assertTrue($super->can('update', $lead));

        foreach (['admin', 'tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            $this->assertFalse($this->a[$perfil]->can('viewAny', LandingPageLead::class), $perfil);
            $this->assertFalse($this->a[$perfil]->can('update', $lead), $perfil);
            $this->assertFalse($this->a[$perfil]->can('delete', $lead), $perfil);
        }
    }

    public function test_permissao_individual_nao_abre_o_modulo_para_tenant(): void
    {
        $this->conceder($this->a['colaborador'], 'lead', ['ler', 'criar', 'editar', 'excluir']);
        $this->actingAs($this->a['colaborador']);

        $this->assertFalse($this->a['colaborador']->can('viewAny', LandingPageLead::class));
    }

    public function test_listagem_da_central_mostra_os_leads_ao_super_admin(): void
    {
        $lead = $this->lead();
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->actingAs($this->superAdmin());

        Livewire::test(LandingPageLeadResource::getPages()['index']->getPage())
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$lead]);
    }

    public function test_listagem_da_central_e_barrada_para_admin_de_tenant(): void
    {
        $this->lead();
        Filament::setCurrentPanel(Filament::getPanel('central'));

        foreach (['admin', 'tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            $this->assertFalse(LandingPageLeadResource::canViewAny(), $perfil);
        }
    }

    public function test_lead_novo_entra_com_os_campos_informados(): void
    {
        $lead = $this->lead(['notes' => 'Quer demo do modulo de frota']);

        $this->assertSame('novo', $lead->fresh()->status);
        $this->assertSame('Quer demo do modulo de frota', $lead->fresh()->notes);
        $this->assertNull($lead->fresh()->contacted_at);
    }

    public function test_rota_de_leads_exige_login(): void
    {
        $this->lead(['name' => 'Pessoa Exposta', 'email' => 'exposta@exemplo.com.br']);

        $resposta = $this->get('/leads');

        $resposta->assertRedirect();
        $this->assertStringNotContainsString('exposta@exemplo.com.br', (string) $resposta->getContent());
    }

    public function test_rota_de_leads_e_barrada_para_usuario_de_tenant(): void
    {
        $this->lead(['email' => 'exposta@exemplo.com.br']);

        foreach (['admin', 'tecnico', 'colaborador'] as $perfil) {
            $resposta = $this->actingAs($this->a[$perfil])->get('/leads');

            $resposta->assertForbidden();
            $this->assertStringNotContainsString('exposta@exemplo.com.br', (string) $resposta->getContent());
        }
    }

    public function test_rota_de_leads_mostra_os_leads_ao_super_admin(): void
    {
        $this->lead(['name' => 'Carlos Lead', 'email' => 'carlos@exemplo.com.br']);

        $this->actingAs($this->superAdmin())->get('/leads')->assertOk()->assertSee('Carlos Lead');
    }
}
