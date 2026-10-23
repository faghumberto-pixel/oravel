<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\JobRoleResource;
use App\Filament\Resources\MaterialStockTakeResource;
use App\Models\Department;
use App\Models\GoodsReceipt;
use App\Models\InternalUnit;
use App\Models\JobRole;
use App\Models\MaterialStockTake;
use App\Models\User;
use App\Support\TenantScopedRelationshipRule;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Throwable;

/**
 * Todo Select/CheckboxList por ->relationship() valida no servidor que o id
 * existe no escopo do tenant (App\Support\TenantScopedRelationshipRule). Antes o
 * Filament so filtrava as opcoes na tela e aceitava qualquer id forjado.
 */
class SelectsValidamTenantTest extends PerfisTestCase
{
    public function test_regra_esta_registrada_nos_dois_componentes(): void
    {
        $this->assertInstanceOf(\Closure::class, TenantScopedRelationshipRule::regra(Select::make('x')));
        $this->assertInstanceOf(\Closure::class, TenantScopedRelationshipRule::regra(CheckboxList::make('x')));
    }

    public function test_todo_select_por_relacao_dos_formularios_de_criacao_rejeita_id_inexistente(): void
    {
        $this->actingAs($this->a['admin']);

        $semRegra = [];
        $verificados = 0;

        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $arquivo) {
            $resource = 'App\\Filament\\Resources\\'.basename($arquivo, '.php');
            $pagina = (new \ReflectionClass($resource))->isAbstract() ? null : ($resource::getPages()['create'] ?? null)?->getPage();
            if (! $pagina) {
                continue;
            }

            try {
                $livewire = Livewire::test($pagina);
                $campos = collect($livewire->instance()->form->getFlatComponents())
                    ->filter(fn ($c) => ($c instanceof Select || $c instanceof CheckboxList)
                        && $c->hasRelationship() && ! $c->isHidden() && ! $c->isDisabled()
                        && substr_count($c->getStatePath(), '.') === 1);

                foreach ($campos as $campo) {
                    $livewire->set($campo->getStatePath(), ($campo instanceof CheckboxList || $campo->isMultiple()) ? [(string) Str::uuid()] : (string) Str::uuid());
                }
                $livewire->call('create');

                foreach ($campos as $campo) {
                    $verificados++;
                    if (! $livewire->errors()->has($campo->getStatePath())) {
                        $semRegra[] = basename($arquivo, 'Resource.php').'.'.$campo->getName();
                    }
                }
            } catch (Throwable $e) {
                // pagina que nao abre/nao salva nesse contexto: coberta pelo ModulosRenderizamTest
            }
        }

        $this->assertGreaterThan(15, $verificados, 'a varredura encontrou poucos campos');
        $this->assertSame([], $semRegra, 'Select por relacao sem validacao de tenant no servidor');
    }

    public function test_funcao_nao_aceita_departamento_de_outro_tenant_mas_aceita_o_proprio(): void
    {
        $doB = $this->criar(Department::class, ['tenant_id' => $this->tenantB->id, 'name' => 'Dep B']);
        $doA = $this->criar(Department::class, ['tenant_id' => $this->tenantA->id, 'name' => 'Dep A']);
        $this->actingAs($this->a['admin']);
        $pagina = JobRoleResource::getPages()['index']->getPage();

        Livewire::test($pagina)->callTableAction('create', data: ['name' => 'Invasora', 'department_id' => $doB->id])
            ->assertHasTableActionErrors(['department_id']);
        Livewire::test($pagina)->callTableAction('create', data: ['name' => 'Legitima', 'department_id' => $doA->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame(0, JobRole::withoutGlobalScopes()->where('name', 'Invasora')->count());
        $this->assertSame(1, JobRole::withoutGlobalScopes()->where('name', 'Legitima')->count());
    }

    public function test_recebimento_e_inventario_rejeitam_almoxarifado_de_outro_tenant(): void
    {
        $unidadeB = $this->criar(InternalUnit::class, ['tenant_id' => $this->tenantB->id, 'name' => 'Almox B']);
        $this->actingAs($this->a['admin']);

        Livewire::test(MaterialStockTakeResource::getPages()['create']->getPage())
            ->fillForm(['internal_unit_id' => $unidadeB->id, 'conducted_by_user_id' => $this->a['admin']->id])
            ->call('create')
            ->assertHasFormErrors(['internal_unit_id']);

        $this->assertSame(0, MaterialStockTake::withoutGlobalScopes()->where('internal_unit_id', $unidadeB->id)->count());

        Livewire::test(GoodsReceiptResource::getPages()['create']->getPage())
            ->fillForm(['internal_unit_id' => $unidadeB->id, 'received_by_user_id' => $this->a['admin']->id, 'received_at' => now()->toDateTimeString()])
            ->call('create')
            ->assertHasFormErrors(['internal_unit_id']);

        $this->assertSame(0, GoodsReceipt::withoutGlobalScopes()->where('internal_unit_id', $unidadeB->id)->count());
    }

    public function test_edicao_nao_trava_quando_o_valor_antigo_ja_estava_salvo(): void
    {
        $unidade = $this->criar(InternalUnit::class, ['tenant_id' => $this->tenantA->id, 'name' => 'Almox A']);
        $inventario = $this->criar(MaterialStockTake::class, [
            'tenant_id' => $this->tenantA->id, 'internal_unit_id' => $unidade->id, 'conducted_by_user_id' => $this->a['admin']->id,
        ]);
        $this->actingAs($this->a['admin']);

        Livewire::test(MaterialStockTakeResource::getPages()['edit']->getPage(), ['record' => $inventario->getKey()])
            ->fillForm(['notes' => 'Revisado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Revisado', $inventario->fresh()->notes);
    }

    public function test_super_admin_nao_e_bloqueado_pela_regra(): void
    {
        config(['oravel.super_admins' => ['super-sel@oravel.com.br']]);
        $super = User::create(['name' => 'Super', 'email' => 'super-sel@oravel.com.br', 'password' => bcrypt('x'), 'is_approved' => true]);
        $doB = $this->criar(Department::class, ['tenant_id' => $this->tenantB->id, 'name' => 'Dep B']);
        $this->actingAs($super);

        $this->assertTrue($super->isSuperAdmin());
        $erros = [];
        TenantScopedRelationshipRule::regra(Select::make('department_id')->relationship('department', 'name'))('department_id', $doB->id, function ($m) use (&$erros) {
            $erros[] = $m;
        });
        $this->assertSame([], $erros);
    }
}
