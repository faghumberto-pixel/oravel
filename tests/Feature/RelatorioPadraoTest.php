<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cabeçalho/rodapé padrão dos relatórios: logo, data/hora, usuário, nome do relatório, filtros e "Oravel" no rodapé. */
class RelatorioPadraoTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(?string $logo = null): array
    {
        $plan = Plan::create(['name' => 'Plano Rel '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'Locadora Rel', 'slug' => 'rel-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active', 'logo_path' => $logo]);
        $user = User::create(['name' => 'Maria Relatório', 'email' => 'rel-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $user];
    }

    public function test_ficha_do_ativo_tem_cabecalho_e_rodape_padrao_com_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tenant-logos/x.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        [$tenant, $user] = $this->admin('tenant-logos/x.png');
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste X', 'status' => 'disponivel']);

        $html = $this->actingAs($user)->get(route('asset.print-cadastro', ['asset' => $asset->id]))->assertOk()->getContent();

        $this->assertStringContainsString('data:image/png;base64,', $html);   // logotipo no topo
        $this->assertStringContainsString('Locadora Rel', $html);
        $this->assertStringContainsString('Emitido por Maria Relatório', $html); // usuário
        $this->assertStringContainsString(now()->format('d/m/Y'), $html);        // data
        $this->assertStringContainsString('Cadastro do Ativo', $html);           // nome do relatório
        $this->assertStringContainsString('Filtros:', $html);
        $this->assertMatchesRegularExpression('/<strong[^>]*>Oravel<\/strong>/', $html); // rodapé
    }

    public function test_relatorio_sem_logo_nao_quebra_e_lista_filtros(): void
    {
        [, $user] = $this->admin();

        $html = view('partials.relatorio-cabecalho', ['tituloRelatorio' => 'Teste', 'filtrosRelatorio' => ['Status: ativo', null, 'Cidade: SP']])->render();
        $this->actingAs($user);
        $html = view('partials.relatorio-cabecalho', ['tituloRelatorio' => 'Teste', 'filtrosRelatorio' => ['Status: ativo', null, 'Cidade: SP']])->render();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Status: ativo · Cidade: SP', $html);
        $this->assertStringContainsString('TESTE', mb_strtoupper($html));
    }
}
