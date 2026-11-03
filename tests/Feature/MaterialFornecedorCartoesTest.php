<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmLeadResource\Pages\ListCrmLeads;
use App\Filament\Resources\CrmLeadResource\Widgets\CrmLeadStats;
use App\Filament\Resources\CrmLeadResource\Widgets\CrmMinhasMetasStats;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\MaterialResource\Pages\ListMaterials;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Materiais e Fornecedores em cartoes (padrao CRM Comercial), sem graficos; CRM Leads sem graficos (pedido 2026-11-03). */
class MaterialFornecedorCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano MF '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_materials', 'tabela_suppliers', 'tabela_crm_leads'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'mf-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $tenant;
    }

    private function material(Tenant $t, string $nome, array $o = []): Material
    {
        return Material::create($o + ['tenant_id' => $t->id, 'sku' => 'SKU-'.uniqid(), 'name' => $nome, 'unit_of_measure' => 'un', 'current_stock' => 10, 'min_stock' => 2, 'unit_cost' => 5]);
    }

    public function test_materiais_em_cartoes_sem_widgets_e_cards_filtram(): void
    {
        $t = $this->cenario();
        $forn = Supplier::create(['tenant_id' => $t->id, 'name' => 'Forn Material']);
        $ok = $this->material($t, 'Parafuso Ok', ['supplier_id' => $forn->id]);
        $baixo = $this->material($t, 'Oleo Baixo', ['supplier_id' => $forn->id, 'current_stock' => 1, 'min_stock' => 5]);
        $sem = $this->material($t, 'Filtro Sem Fornecedor');

        $this->assertSame('emerald', MaterialResource::corDoMaterial($ok));
        $this->assertSame('red', MaterialResource::corDoMaterial($baixo));
        $this->assertSame('amber', MaterialResource::corDoMaterial($sem));
        $this->assertSame([], (new \ReflectionMethod(ListMaterials::class, 'getHeaderWidgets'))->invoke(new ListMaterials));

        $lista = Livewire::test(ListMaterials::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-emerald-600')->assertSeeHtml('border-red-600')->assertSeeHtml('border-amber-600');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(1, $cards['baixo']['total']);
        $this->assertSame(1, $cards['sem_fornecedor']['total']);
        $this->assertSame(2, $cards['com_fornecedor']['total']);

        $lista->call('filtrarCard', 'baixo')
            ->assertCanSeeTableRecords([$baixo])->assertCanNotSeeTableRecords([$ok, $sem])
            ->call('filtrarCard', 'sem_fornecedor')
            ->assertCanSeeTableRecords([$sem])->assertCanNotSeeTableRecords([$ok, $baixo])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$ok, $baixo, $sem]);
    }

    public function test_fornecedores_em_cartoes_sem_widgets_e_cards_filtram(): void
    {
        $t = $this->cenario();
        $completo = Supplier::create(['tenant_id' => $t->id, 'name' => 'Completo', 'compliance_ceis_cnep' => true, 'lista_trabalho_escravo' => true, 'termo_lgpd' => true,
            'cnd_federal' => now()->addYear(), 'crf_fgts' => now()->addYear(), 'cndt' => now()->addYear()]);
        $pendente = Supplier::create(['tenant_id' => $t->id, 'name' => 'Pendente', 'compliance_ceis_cnep' => true, 'lista_trabalho_escravo' => true, 'termo_lgpd' => true]);
        $semLgpd = Supplier::create(['tenant_id' => $t->id, 'name' => 'Sem Lgpd']);

        $this->assertSame('emerald', SupplierResource::corDoFornecedor($completo));
        $this->assertSame('amber', SupplierResource::corDoFornecedor($pendente));
        $this->assertSame('red', SupplierResource::corDoFornecedor($semLgpd));
        $this->assertSame([], (new \ReflectionMethod(ListSuppliers::class, 'getHeaderWidgets'))->invoke(new ListSuppliers));

        $lista = Livewire::test(ListSuppliers::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-emerald-600')->assertSeeHtml('border-red-600');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(2, $cards['compliance']['total']);
        $this->assertSame(2, $cards['certidoes']['total']);
        $this->assertSame(1, $cards['sem_lgpd']['total']);

        $lista->call('filtrarCard', 'sem_lgpd')
            ->assertCanSeeTableRecords([$semLgpd])->assertCanNotSeeTableRecords([$completo, $pendente])
            ->call('filtrarCard', 'certidoes')
            ->assertCanSeeTableRecords([$pendente, $semLgpd])->assertCanNotSeeTableRecords([$completo])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$completo, $pendente, $semLgpd]);
    }

    public function test_crm_leads_sem_graficos_so_cards_de_numeros(): void
    {
        $this->cenario();

        $widgets = (new \ReflectionMethod(ListCrmLeads::class, 'getHeaderWidgets'))->invoke(new ListCrmLeads);
        $this->assertSame([
            CrmMinhasMetasStats::class,
            CrmLeadStats::class,
        ], $widgets);
    }
}
