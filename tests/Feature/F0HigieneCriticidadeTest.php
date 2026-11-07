<?php

namespace Tests\Feature;

use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\CriticalityLevel;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** F0 da integracao criticidade/prioridade/SLA: correcoes de higiene que independem das decisoes em aberto. */
class F0HigieneCriticidadeTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(): Tenant
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true]]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f0-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function nivel(Tenant $t, string $code, bool $urgente = false, int $ordem = 1): CriticalityLevel
    {
        return CriticalityLevel::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'code' => $code, 'name' => ucfirst($code), 'color' => '#ef4444', 'is_urgent' => $urgente, 'sort_order' => $ordem]);
    }

    public function test_letra_do_nivel_funciona_com_codigos_padrao_e_nao_padrao(): void
    {
        $t = $this->tenant();

        $this->assertSame('A', $this->nivel($t, 'A', true)->letter);
        $this->assertSame('B', $this->nivel($t, 'media')->letter);
        $this->assertSame('A', $this->nivel($t, 'urgente', true)->letter);
        $this->assertSame('C', $this->nivel($t, 'baixa')->letter);
    }

    public function test_ativo_com_nivel_urgente_dispara_o_alerta_mesmo_com_peso_1(): void
    {
        $t = $this->tenant();
        $this->nivel($t, 'A', true);
        $admin = User::create(['name' => 'Adm', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'email_verified_at' => now()]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($admin);

        $ativo = Asset::create(['tenant_id' => $t->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => 'operando', 'criticidade_peso' => 1]);
        AbcMatrix::create(['tenant_id' => $t->id, 'asset_id' => $ativo->id, 'nivel' => 'A', 'descricao' => 'Essencial']);

        $ativo->update(['status' => 'manutencao']);

        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_ativo_sem_nivel_urgente_nao_dispara_o_alerta(): void
    {
        $t = $this->tenant();
        $this->nivel($t, 'C', false, 3);
        $admin = User::create(['name' => 'Adm', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'email_verified_at' => now()]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($admin);

        $ativo = Asset::create(['tenant_id' => $t->id, 'name' => 'Compressor', 'tag' => 'AST-'.uniqid(), 'status' => 'operando', 'criticidade_peso' => 1]);
        AbcMatrix::create(['tenant_id' => $t->id, 'asset_id' => $ativo->id, 'nivel' => 'C', 'descricao' => 'Comum']);

        $ativo->update(['status' => 'manutencao']);

        $this->assertSame(0, $admin->fresh()->notifications()->count());
    }

    public function test_comando_cria_niveis_padrao_so_com_apply_e_nao_mexe_em_quem_ja_tem(): void
    {
        $semNiveis = $this->tenant();
        $comNiveis = $this->tenant();
        $this->nivel($comNiveis, 'alta', true);

        $this->artisan('criticality:normalizar')->assertSuccessful();
        $this->assertSame(0, CriticalityLevel::withoutGlobalScopes()->where('tenant_id', $semNiveis->id)->count());

        $this->artisan('criticality:normalizar', ['--apply' => true])->assertSuccessful();
        $this->assertSame(['A', 'B', 'C'], CriticalityLevel::withoutGlobalScopes()->where('tenant_id', $semNiveis->id)->orderBy('sort_order')->pluck('code')->all());
        $this->assertSame(['alta'], CriticalityLevel::withoutGlobalScopes()->where('tenant_id', $comNiveis->id)->pluck('code')->all());

        $this->artisan('criticality:normalizar', ['--apply' => true])->assertSuccessful();
        $this->assertSame(3, CriticalityLevel::withoutGlobalScopes()->where('tenant_id', $semNiveis->id)->count());
    }
}
