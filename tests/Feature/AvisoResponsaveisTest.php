<?php

namespace Tests\Feature;

use App\Models\AvisoResponsavel;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DestinatariosAvisos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AvisoResponsaveisTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): Tenant
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function usuario(Tenant $tenant, string $nome, ?string $papel = null): User
    {
        $u = User::create(['name' => $nome, 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        if ($papel) {
            $u->assignRole(Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        }

        return $u;
    }

    public function test_pessoa_escolhida_recebe_e_vence_o_papel_antigo(): void
    {
        $e = $this->empresa();
        $comercial = $this->usuario($e, 'Papel Comercial', 'Comercial');
        $maria = $this->usuario($e, 'Maria');
        AvisoResponsavel::withoutGlobalScopes()->create(['tenant_id' => $e->id, 'evento' => 'proposta_para_revisao', 'user_id' => $maria->id]);

        $ids = DestinatariosAvisos::para($e->id, 'proposta_para_revisao')->pluck('id');

        $this->assertEquals([$maria->id], $ids->all());
        $this->assertNotContains($comercial->id, $ids->all());
    }

    public function test_sem_escolha_usa_o_papel_que_o_sistema_ja_usava(): void
    {
        $e = $this->empresa();
        $comercial = $this->usuario($e, 'Papel Comercial', 'Comercial');
        $this->usuario($e, 'Admin', 'admin');

        $this->assertEquals([$comercial->id], DestinatariosAvisos::para($e->id, 'proposta_para_revisao')->pluck('id')->all());
    }

    public function test_empresa_sem_comercial_cai_nos_administradores_e_nao_perde_o_aviso(): void
    {
        $e = $this->empresa();
        $admin = $this->usuario($e, 'Admin', 'admin');
        $this->usuario($e, 'Qualquer um');

        $this->assertEquals([$admin->id], DestinatariosAvisos::para($e->id, 'proposta_para_revisao')->pluck('id')->all());
    }

    public function test_nao_mistura_empresas(): void
    {
        $a = $this->empresa();
        $b = $this->empresa();
        $this->usuario($a, 'Comercial A', 'Comercial');
        $adminB = $this->usuario($b, 'Admin B', 'admin');

        $this->assertEquals([$adminB->id], DestinatariosAvisos::para($b->id, 'proposta_para_revisao')->pluck('id')->all());
    }

    public function test_tela_salva_as_pessoas_escolhidas_por_aviso(): void
    {
        $e = $this->empresa();
        $admin = $this->usuario($e, 'Admin', 'admin');
        $maria = $this->usuario($e, 'Maria');
        $joao = $this->usuario($e, 'Joao');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\ResponsaveisAvisos::class)
            ->fillForm(['evento' => ['proposta_para_revisao' => [$maria->id, $joao->id], 'estoque_minimo' => [$maria->id]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$maria->id, $joao->id], DestinatariosAvisos::para($e->id, 'proposta_para_revisao')->pluck('id')->all());

        \Livewire\Livewire::test(\App\Filament\Pages\ResponsaveisAvisos::class)
            ->fillForm(['evento' => ['proposta_para_revisao' => [$joao->id], 'estoque_minimo' => []]])
            ->call('save');

        $this->assertEquals([$joao->id], DestinatariosAvisos::para($e->id, 'proposta_para_revisao')->pluck('id')->all());
        $this->assertEquals([$admin->id], DestinatariosAvisos::para($e->id, 'estoque_minimo')->pluck('id')->all(), 'sem ninguem escolhido volta ao padrao (admin)');
    }
}
