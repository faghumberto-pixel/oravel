<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao: a aba "Logs de Auditoria" era um Repeater com
 * ->relationship('activities') e um campo 'causer.name' (notacao de ponto,
 * so pra exibir o nome do usuario). Ao salvar o form do Ativo, o Filament
 * tenta sincronizar cada Activity existente de volta pro banco -- o campo
 * dot-notation deixava um 'causer' => [] vazando no payload mesmo
 * desabilitado, e como Activity tem $guarded = [], o Eloquent tentava
 * gravar uma coluna "causer" que nao existe (a real e causer_type/
 * causer_id), quebrando TODA edicao de um ativo que ja tivesse pelo menos
 * 1 log de atividade. So foi reproduzido salvando o form de verdade via
 * Livewire -- um Asset::update() direto via Eloquent nao expõe o bug.
 * Corrigido trocando o Repeater por um Placeholder somente-leitura.
 */
class AssetResourceEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Edicao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Edicao '.uniqid(), 'slug' => 'tenant-edicao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_editing_an_asset_with_existing_activity_history_saves_without_error(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste',
            'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'asset_category' => $category->name,
            'patrimonio' => 'PAT-'.uniqid(),
            'acquisition_value' => 1000, 'acquisition_date' => now()->subYear(),
        ]);
        // Garante um segundo log (update) alem do de criacao, pra exercitar
        // o Repeater/Placeholder com mais de uma linha de historico.
        $asset->update(['description' => 'Revisado.']);

        $this->assertGreaterThanOrEqual(2, $asset->activities()->count());

        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->fillForm(['name' => 'Gerador Teste Atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Gerador Teste Atualizado', $asset->fresh()->name);
    }

    /**
     * Regressao: a coluna 'checklist' e jsonb, mas o model Asset nao tinha
     * cast pra array -- lida de volta como string bruta ('[]', nao um
     * array PHP de verdade). O Repeater da aba "Checklist de Verificacao"
     * (Forms\Components\Repeater::make('checklist'), sem ->relationship())
     * faz foreach() sobre esse valor ao abrir o form, quebrando com
     * "foreach() argument must be of type array|object, string given"
     * pra qualquer ativo que ja tivesse checklist preenchido (nao nulo).
     * Reproduzido gravando a string bruta direto, ignorando o cast, pra
     * simular o estado real de dados gravados antes da correcao.
     */
    public function test_asset_with_raw_json_checklist_value_opens_without_error(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Plataforma Teste',
            'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
        ]);
        // Simula o dado real ja gravado antes do cast existir.
        DB::table('assets')->where('id', $asset->id)->update(['checklist' => '[]']);

        $response = $this->get(AssetResource::getUrl('edit', ['record' => $asset->fresh()->id]));

        $response->assertOk();
    }

    public function test_asset_edit_page_shows_activity_history_and_correct_tag_field(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Compressor Teste',
            'tag' => 'TAG-9999', 'status' => Asset::STATUS_DISPONIVEL,
        ]);

        $response = $this->get(AssetResource::getUrl('edit', ['record' => $asset]));

        $response->assertOk();
        $response->assertSee('Histórico de Alterações no Cadastro');
        $response->assertSee('Criação');
        $response->assertSee($admin->name);
    }
}
