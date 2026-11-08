<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Models\Asset;
use App\Models\CatalogoItem;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * G1: no cadastro do ativo havia dois campos de nome parecido: "Categoria e Tipo" (o grupo do catalogo) e "Grupo" (o grupo de
 * CHECKLIST, cadastro antigo e vazio na maioria das empresas). Com a chave do catalogo ligada os rotulos passam a dizer o que sao.
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class G1RotulosGrupoAtivoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array{0: Tenant, 1: User} */
    private function tenantComAdmin(bool $comChave): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comChave ? [CatalogoItem::FEATURE_KEY => true] : []) + ['tabela_assets' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1r-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return [$t, $u];
    }

    public function test_com_a_chave_ligada_os_rotulos_dizem_o_que_cada_campo_e(): void
    {
        [$t, $u] = $this->tenantComAdmin(true);
        $this->actingAs($u);

        $this->assertSame('Grupo de equipamento', AssetResource::rotuloGrupoEquipamento('Categoria e Tipo'));
        $this->assertSame('Grupo de checklist', AssetResource::rotuloGrupoChecklist());

        $a = Asset::factory()->create(['tenant_id' => $t->id, 'asset_category_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        Livewire::test(EditAsset::class, ['record' => $a->getKey()])
            ->assertFormFieldExists('asset_category_id', fn ($campo) => $campo->getLabel() === 'Grupo de equipamento')
            ->assertFormFieldExists('checklist_group_id', fn ($campo) => $campo->getLabel() === 'Grupo de checklist');
    }

    public function test_sem_a_chave_os_rotulos_continuam_os_de_sempre(): void
    {
        [$t, $u] = $this->tenantComAdmin(false);
        $this->actingAs($u);

        $this->assertSame('Categoria e Tipo', AssetResource::rotuloGrupoEquipamento('Categoria e Tipo'));
        $this->assertSame('Categoria', AssetResource::rotuloGrupoEquipamento('Categoria'));
        $this->assertSame('Grupo', AssetResource::rotuloGrupoChecklist());

        $a = Asset::factory()->create(['tenant_id' => $t->id, 'asset_category_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        Livewire::test(EditAsset::class, ['record' => $a->getKey()])
            ->assertFormFieldExists('asset_category_id', fn ($campo) => $campo->getLabel() === 'Categoria e Tipo')
            ->assertFormFieldExists('checklist_group_id', fn ($campo) => $campo->getLabel() === 'Grupo');
    }
}
