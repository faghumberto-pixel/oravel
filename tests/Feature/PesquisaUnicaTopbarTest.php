<?php

namespace Tests\Feature;

use App\Filament\Resources\StockLocationResource;
use App\Livewire\ScreenSearch;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** O topbar tem um campo de pesquisa só: o da ScreenSearch (a pesquisa global nativa não achava nada e foi desligada). */
class PesquisaUnicaTopbarTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_materials' => true, 'tabela_stock_structure' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'pt-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Ana', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_topbar_tem_so_o_campo_de_pesquisa_de_telas_e_sem_a_pesquisa_global_nativa(): void
    {
        $pagina = $this->get(StockLocationResource::getUrl('index'))->assertOk();

        $pagina->assertSee('Pesquisar tela...', false);
        $pagina->assertDontSee('fi-global-search', false);
        $this->assertSame(1, substr_count($pagina->getContent(), 'wire:model.live.debounce.150ms="query"'));
    }

    public function test_digitar_mostra_as_telas_que_combinam_com_o_termo(): void
    {
        Livewire::test(ScreenSearch::class)
            ->set('query', 'endereco')
            ->assertSee('Endereços Do Almoxarifado')
            ->set('query', 'zzzxxqq')
            ->assertSee('Nenhuma tela encontrada.');
    }
}
