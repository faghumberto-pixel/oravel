<?php

namespace Tests\Feature;

use App\Filament\Pages\PrimeirosPassos;
use App\Filament\Resources\AssetResource;
use App\Mail\OnboardingLembreteMail;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingLembretes;
use App\Support\AppProfile;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Onboarding da solução Manutenção: 3 equipamentos, primeira OS e primeiro plano preventivo, por dados reais. */
class OnboardingManutencaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(): Tenant
    {
        $plano = Pacotes::planoDeVitrine('manutencao');
        $plano->update(['is_active' => true]);

        return Tenant::create(['name' => 'Oficina '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function ativo(Tenant $t, int $n): Asset
    {
        return Asset::withoutGlobalScopes()->forceCreate(['tenant_id' => $t->id, 'name' => "Equipamento {$n}", 'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel']);
    }

    private function servico(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    public function test_the_steps_complete_by_real_data(): void
    {
        $t = $this->empresa();
        $e = $this->servico()->estado($t);
        $this->assertSame(['cadastrar_equipamentos', 'primeira_os', 'primeiro_plano_preventivo'], array_column($e['passos'], 'chave'));
        $this->assertSame(20, $e['minutos_restantes']);

        $this->ativo($t, 1);
        $this->ativo($t, 2);
        $this->assertFalse($this->servico()->estado($t)['passos'][0]['concluido'], 'precisa de 3 equipamentos');

        $this->ativo($t, 3);
        $this->assertSame([true, false, false], array_column($this->servico()->estado($t)['passos'], 'concluido'));

        MaintenancePlan::withoutGlobalScopes()->forceCreate(['tenant_id' => $t->id, 'name' => 'Troca de óleo', 'asset_id' => Asset::withoutGlobalScopes()->where('tenant_id', $t->id)->value('id'), 'interval_hours' => 250, 'is_active' => true]);
        $this->assertSame([true, false, true], array_column($this->servico()->estado($t)['passos'], 'concluido'));
    }

    public function test_each_company_counts_only_its_own_equipment(): void
    {
        $a = $this->empresa();
        $b = $this->empresa();
        foreach ([1, 2, 3] as $n) {
            $this->ativo($a, $n);
        }

        $this->assertTrue($this->servico()->estado($a)['passos'][0]['concluido']);
        $this->assertFalse($this->servico()->estado($b)['passos'][0]['concluido']);
    }

    public function test_screen_shows_the_maintenance_steps_with_direct_buttons(): void
    {
        $t = $this->empresa();
        $u = User::create(['name' => 'Rita Alves', 'email' => 'r-'.uniqid().'@oficina.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($u);

        $this->assertSame(PrimeirosPassos::getUrl(), AppProfile::homeUrl($u, AppProfile::ADMIN));
        Livewire::test(PrimeirosPassos::class)->assertSuccessful()->assertSee('Bem-vindo, Rita')
            ->assertSee('Faltam 3 passos para abrir sua primeira ordem de serviço. Leva cerca de 20 minutos.')
            ->assertSee('Cadastre seus equipamentos')->assertSee('Abra sua primeira ordem de serviço')->assertSee('Crie seu primeiro plano preventivo')
            ->assertSee('Cadastrar equipamento')->assertSee(AssetResource::getUrl('create'), false)
            ->assertDontSee('Baixar planilha modelo');
    }

    public function test_reminder_text_follows_the_solution_and_never_mentions_clients_or_proposals(): void
    {
        Mail::fake();
        $t = $this->empresa();
        $u = User::create(['name' => 'Adm', 'email' => 'adm-'.uniqid().'@oficina.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $t->forceFill(['created_at' => Carbon::parse('2026-10-20 08:00', 'America/Sao_Paulo')->subDays(2)])->save();

        app(OnboardingLembretes::class)->executar(Carbon::parse('2026-10-20 10:00', 'America/Sao_Paulo'));

        Mail::assertSent(OnboardingLembreteMail::class, function (OnboardingLembreteMail $m) {
            $texto = $m->assunto.' '.implode(' ', $m->paragrafos);

            return str_contains($texto, 'Cadastre seus equipamentos') && ! str_contains(mb_strtolower($texto), 'cliente') && ! str_contains(mb_strtolower($texto), 'proposta');
        });
    }

    public function test_completed_maintenance_onboarding_shows_its_own_closing_message(): void
    {
        $t = $this->empresa();
        $ativos = array_map(fn ($n) => $this->ativo($t, $n), [1, 2, 3]);
        MaintenanceOrder::withoutGlobalScopes()->forceCreate(['tenant_id' => $t->id, 'asset_id' => $ativos[0]->id, 'status' => 'open', 'description' => 'Primeira', 'os_number' => 'OS-1']);
        MaintenancePlan::withoutGlobalScopes()->forceCreate(['tenant_id' => $t->id, 'name' => 'P', 'asset_id' => $ativos[0]->id, 'interval_hours' => 100, 'is_active' => true]);

        $e = $this->servico()->estado($t);

        $this->assertTrue($e['completo']);
        $this->assertStringContainsString('primeira ordem de serviço', $e['conclusao']);
    }
}
