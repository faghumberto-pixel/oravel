<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\Tenant;
use App\Support\SaaSRegistry;
use Illuminate\Database\Eloquent\Model;

/** Contatos do lead de CRM: vivem dentro do lead (relation manager), sem tela propria. */
class CrmLeadContactTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return CrmLeadContact::class;
    }

    private function lead(Tenant $tenant): CrmLead
    {
        return $this->criar(CrmLead::class, ['tenant_id' => $tenant->id, 'name' => 'Lead '.uniqid()]);
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(CrmLeadContact::class, [
            'tenant_id' => $tenant->id, 'crm_lead_id' => $this->lead($tenant)->id, 'name' => 'Contato '.uniqid(),
        ]);
    }

    /** Sem policy/plano proprios: o acesso ao contato e o do lead (CrmLead). */
    protected function slug(): string
    {
        return SaaSRegistry::forModel(CrmLead::class)['slug'];
    }

    protected function feature(): string
    {
        return SaaSRegistry::forModel(CrmLead::class)['feature'];
    }

    public function test_permissao_de_leitura_libera_so_a_listagem(): void
    {
        $this->pularPoisContatoSegueOLead();
    }

    public function test_cada_acao_exige_a_sua_permissao(): void
    {
        $this->pularPoisContatoSegueOLead();
    }

    public function test_modulo_fora_do_plano_nega_ate_o_administrador(): void
    {
        $this->pularPoisContatoSegueOLead();
    }

    private function pularPoisContatoSegueOLead(): void
    {
        $this->markTestSkipped('contato nao tem policy propria; coberto por test_acesso_ao_contato_segue_o_acesso_ao_lead');
    }

    public function test_acesso_ao_contato_segue_o_acesso_ao_lead(): void
    {
        $this->actingAs($this->a['admin']);
        $this->assertTrue($this->a['admin']->can('viewAny', CrmLead::class));

        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            $this->assertFalse($this->a[$perfil]->can('viewAny', CrmLead::class), $perfil.' sem permissao');
        }

        $this->conceder($this->a['colaborador'], $this->slug(), ['ler']);
        $this->actingAs($this->a['colaborador']);
        $this->assertTrue($this->a['colaborador']->can('viewAny', CrmLead::class));
        $this->assertFalse($this->a['colaborador']->can('create', CrmLead::class));
    }

    public function test_contato_pertence_ao_lead_e_aparece_na_relacao(): void
    {
        $lead = $this->lead($this->tenantA);
        $contato = $this->criar(CrmLeadContact::class, [
            'tenant_id' => $this->tenantA->id, 'crm_lead_id' => $lead->id, 'name' => 'Joana Compras',
            'role_title' => 'Gerente', 'email' => 'joana@cliente.com.br', 'is_primary' => true,
        ]);
        $this->actingAs($this->a['admin']);

        $this->assertTrue($contato->lead->is($lead));
        $this->assertTrue($lead->contacts->contains($contato));
        $this->assertTrue($contato->fresh()->is_primary);
    }

    public function test_lead_de_um_tenant_nao_lista_contato_de_outro(): void
    {
        $leadA = $this->lead($this->tenantA);
        $contatoDeB = $this->criar(CrmLeadContact::class, [
            'tenant_id' => $this->tenantB->id, 'crm_lead_id' => $leadA->id, 'name' => 'Intruso',
        ]);
        $this->actingAs($this->a['admin']);

        $this->assertFalse($leadA->contacts()->get()->contains('id', $contatoDeB->id));
    }

    public function test_excluir_o_lead_leva_os_contatos_junto(): void
    {
        $contato = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);

        CrmLead::query()->find($contato->crm_lead_id)->forceDelete();

        $this->assertNull(CrmLeadContact::withoutGlobalScopes()->find($contato->id));
    }
}
