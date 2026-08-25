<?php

namespace Tests\Feature;

use App\Filament\Pages\CargaTecnica;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-25 (5º artefato da série, fora do roteiro
 * original de 4): "sei quem está sobrecarregado e quem está ocioso, sem
 * filtrar técnico por técnico?". Investigação encontrou TechnicianOrderStats
 * como autoatendido (só "minhas OS", pro técnico logado) e nenhum conceito
 * de "técnico ocioso" no sistema -- só existia pra Ativos.
 *
 * CargaTecnica::getCargaProperty() lista todos os técnicos com histórico
 * de OS, contando quantas estão em Aberto/Pendente/Em Andamento agora, e
 * marca ocioso quem tem zero. MaintenanceOrderResource::technicianOptionsByWorkload()
 * ordena o Select de técnico do form por essa mesma carga, menos primeiro.
 */
class CargaTecnicaTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Carga '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Carga '.uniqid(), 'slug' => 'tenant-carga-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeUser(Tenant $tenant, string $name): User
    {
        return User::create([
            'name' => $name, 'email' => strtolower($name).'-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
    }

    private function makeOS(Tenant $tenant, User $technician, string $status): MaintenanceOrder
    {
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
        ]);

        return MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'os_number' => 'OS-'.uniqid(), 'status' => $status, 'maintenance_type' => 'corretiva',
        ]);
    }

    public function test_carga_conta_os_em_aberto_e_marca_tecnico_sem_os_como_ocioso(): void
    {
        $tenant = $this->makeTenant();
        $sobrecarregado = $this->makeUser($tenant, 'Joao Sobrecarregado');
        $ocioso = $this->makeUser($tenant, 'Maria Ociosa');

        $this->makeOS($tenant, $sobrecarregado, 'Aberto');
        $this->makeOS($tenant, $sobrecarregado, 'Em Andamento');
        $this->makeOS($tenant, $sobrecarregado, 'Concluída'); // não conta como em aberto

        // Ocioso tem histórico (1 OS), mas já concluída -- zero em aberto agora.
        $this->makeOS($tenant, $ocioso, 'Concluída');

        $this->actingAs($sobrecarregado);
        $page = new CargaTecnica();
        $carga = $page->carga->keyBy(fn ($linha) => $linha['technician']->id);

        $this->assertSame(2, $carga[$sobrecarregado->id]['em_aberto']);
        $this->assertFalse($carga[$sobrecarregado->id]['ocioso']);

        $this->assertSame(0, $carga[$ocioso->id]['em_aberto']);
        $this->assertTrue($carga[$ocioso->id]['ocioso']);
    }

    public function test_carga_nao_lista_usuario_que_nunca_foi_tecnico_de_nada(): void
    {
        $tenant = $this->makeTenant();
        $semHistorico = $this->makeUser($tenant, 'Sem Historico');
        $comHistorico = $this->makeUser($tenant, 'Com Historico');
        $this->makeOS($tenant, $comHistorico, 'Aberto');

        $this->actingAs($comHistorico);
        $page = new CargaTecnica();
        $ids = $page->carga->pluck('technician.id');

        $this->assertTrue($ids->contains($comHistorico->id));
        $this->assertFalse($ids->contains($semHistorico->id));
    }

    public function test_select_de_tecnico_no_form_ordena_menos_carregado_primeiro(): void
    {
        $tenant = $this->makeTenant();
        $ocupado = $this->makeUser($tenant, 'Ocupado');
        $livre = $this->makeUser($tenant, 'Livre');

        $this->makeOS($tenant, $ocupado, 'Aberto');
        $this->makeOS($tenant, $ocupado, 'Pendente');

        $this->actingAs($livre);

        $method = new \ReflectionMethod(MaintenanceOrderResource::class, 'technicianOptionsByWorkload');
        $method->setAccessible(true);

        $options = $method->invoke(null);
        $ids = array_keys($options);

        $this->assertSame($livre->id, $ids[0]);
        $this->assertSame($ocupado->id, $ids[1]);
        $this->assertStringContainsString('livre', $options[$livre->id]);
        $this->assertStringContainsString('2 em aberto', $options[$ocupado->id]);
    }
}
