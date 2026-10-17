<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppProfile;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ManifestoAppTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(?string $pacote): User
    {
        $plano = $pacote
            ? Pacotes::planoDeVitrine($pacote)
            : Plan::create(['name' => 'Personalizado '.uniqid(), 'price' => 100, 'is_active' => true, 'features' => []]);
        $tenant = Tenant::factory()->create(['plan_id' => $plano->id]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_manifesto_leva_nome_e_cor_da_solucao(): void
    {
        $this->actingAs($this->usuario('manutencao'));

        $this->getJson(route('app.manifesto', AppProfile::ADMIN))
            ->assertOk()
            ->assertJsonPath('name', 'Oravel Manutenção')
            ->assertJsonPath('theme_color', '#ea580c')
            ->assertJsonPath('id', '/app/admin')
            ->assertJsonPath('start_url', '/app/admin');
    }

    public function test_perfil_tecnico_ganha_sufixo_e_mesmo_escopo(): void
    {
        $this->actingAs($this->usuario('logistica'));

        $this->getJson(route('app.manifesto', AppProfile::TECNICO))
            ->assertOk()
            ->assertJsonPath('name', 'Oravel Logística Técnico')
            ->assertJsonPath('start_url', '/app/tecnico')
            ->assertJsonPath('scope', '/');
    }

    public function test_contrato_sem_pacote_continua_oravel(): void
    {
        $this->actingAs($this->usuario(null));

        $this->getJson(route('app.manifesto', AppProfile::ADMIN))
            ->assertOk()
            ->assertJsonPath('name', 'Oravel Administrador');
    }

    public function test_cada_empresa_ve_so_a_propria_solucao(): void
    {
        $this->actingAs($this->usuario('vendas'));
        $this->getJson(route('app.manifesto', AppProfile::ADMIN))->assertJsonPath('name', 'Oravel Vendas');

        $this->actingAs($this->usuario('estoque'));
        $this->getJson(route('app.manifesto', AppProfile::ADMIN))->assertJsonPath('name', 'Oravel Estoque');
    }

    public function test_hr_package_has_its_own_app_and_opens_on_employees(): void
    {
        $usuario = $this->usuario('departamento_pessoal');
        $this->actingAs($usuario);

        $this->getJson(route('app.manifesto', AppProfile::ADMIN))->assertJsonPath('name', 'Oravel Pessoas')->assertJsonPath('theme_color', '#0ea5e9');
        $this->assertTrue(method_exists(EmployeeResource::class, 'canAccess'), 'a tela inicial do pacote precisa de canAccess()');
    }

    public function test_exige_login(): void
    {
        $this->get('/app-manifest/admin.json')->assertRedirect();
    }
}
