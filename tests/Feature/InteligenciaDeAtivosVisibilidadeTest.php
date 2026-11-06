<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustoManutencaoService;
use App\Services\LocalizacaoEquipamentosService;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Garantia para a producao: as telas de Inteligencia de Ativos NAO aparecem para quem nao tem a chave no contrato. */
class InteligenciaDeAtivosVisibilidadeTest extends TestCase
{
    use DatabaseTransactions;

    private function entrar(array $features): void
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ia-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

    }

    private function grupos(): array
    {
        return collect(Filament::getNavigation())->map(fn ($g) => $g->getLabel())->filter()->values()->all();
    }

    public function test_menu_nao_mostra_o_grupo_sem_a_chave_no_contrato(): void
    {
        $this->entrar(['tabela_assets', 'tabela_clients', 'tabela_contracts', 'tabela_maintenance_orders']);

        $this->assertNotContains('Inteligência de Ativos', $this->grupos());
    }

    public function test_menu_mostra_o_grupo_com_as_duas_telas_quando_o_contrato_libera(): void
    {
        $this->entrar(['tabela_assets', CustoManutencaoService::FEATURE, LocalizacaoEquipamentosService::FEATURE]);

        $grupo = collect(Filament::getNavigation())->first(fn ($g) => $g->getLabel() === 'Inteligência de Ativos');
        $this->assertNotNull($grupo);
        $this->assertCount(2, $grupo->getItems());
    }

    public function test_nenhum_pacote_vendavel_inclui_as_chaves_novas(): void
    {
        foreach (array_keys(Pacotes::todos()) as $codigo) {
            $features = Pacotes::features($codigo);
            $this->assertNotContains(CustoManutencaoService::FEATURE, $features, "pacote {$codigo}");
            $this->assertNotContains(LocalizacaoEquipamentosService::FEATURE, $features, "pacote {$codigo}");
        }
    }

    public function test_telas_e_aba_respondem_403_sem_a_chave(): void
    {
        $this->entrar(['tabela_assets']);

        $this->get('/admin/custo-manutencao-equipamentos')->assertForbidden();
        $this->get('/admin/localizacao-equipamentos')->assertForbidden();
    }
}
