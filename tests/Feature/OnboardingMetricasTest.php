<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\OnboardingMetricasPage;
use App\Models\Client;
use App\Models\OnboardingInicio;
use App\Models\PropostaComercial;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingMetricas;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Métricas do onboarding: contas por passo, em até 24 h, tempo mediano e acesso só do super admin. */
class OnboardingMetricasTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $pacote = 'servicos'): Tenant
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true]);

        return Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    /** Empresa que fez os passos indicados, concluindo (se todos) $minutosAteConcluir depois da assinatura. */
    private function cenario(int $passosFeitos, ?int $minutosAteConcluir = null): Tenant
    {
        $t = $this->empresa();
        $t->forceFill(['created_at' => now()->subDays(3)])->save();

        if ($passosFeitos >= 1) {
            Client::create(['tenant_id' => $t->id, 'name' => 'C']);
        }
        if ($passosFeitos >= 2) {
            PropostaComercial::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'status' => PropostaComercial::STATUS_APROVADA_INTERNA]);
        }
        if ($passosFeitos >= 3) {
            PropostaComercial::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'status' => PropostaComercial::STATUS_ACEITA_PELO_CLIENTE]);
        }

        app(OnboardingService::class)->sincronizar($t);

        if ($minutosAteConcluir !== null) {
            $inicio = OnboardingInicio::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
            $inicio->forceFill(['iniciado_em' => now()->subDays(3), 'concluido_em' => now()->subDays(3)->addMinutes($minutosAteConcluir)])->save();
        }

        return $t;
    }

    public function test_metrics_count_signups_steps_within_24h_and_median_time(): void
    {
        $this->cenario(3, 180);      // concluiu em 3 h
        $this->cenario(1);           // só o passo 1
        $this->cenario(3, 1800);     // concluiu em 30 h
        $this->cenario(0);           // nada
        $this->empresa('manutencao'); // outra solução: fora da conta

        $m = collect(app(OnboardingMetricas::class)->porSolucao(now()->subDays(30)))->firstWhere('solucao', 'servicos');

        $this->assertSame(4, $m['assinaturas']);
        $this->assertSame([3, 2, 2], array_column($m['passos'], 'concluiram'));
        $this->assertSame([75.0, 50.0, 50.0], array_column($m['passos'], 'percentual'));
        $this->assertSame(2, $m['concluiram_todos']);
        $this->assertSame(50.0, $m['pct_concluiram_todos']);
        $this->assertSame(1, $m['concluiram_em_24h']);
        $this->assertSame(25.0, $m['pct_em_24h']);
        $this->assertSame(990, $m['mediana_minutos'], 'mediana de 180 e 1800 minutos');
    }

    public function test_period_filter_and_empty_state_do_not_divide_by_zero(): void
    {
        $velha = $this->cenario(1);
        $velha->forceFill(['created_at' => now()->subDays(60)])->save();

        $m = collect(app(OnboardingMetricas::class)->porSolucao(now()->subDays(30)))->firstWhere('solucao', 'servicos');
        $this->assertSame(0, $m['assinaturas']);
        $this->assertSame(0.0, $m['pct_em_24h']);
        $this->assertNull($m['mediana_minutos']);

        $todos = collect(app(OnboardingMetricas::class)->porSolucao())->firstWhere('solucao', 'servicos');
        $this->assertGreaterThanOrEqual(1, $todos['assinaturas']);
    }

    public function test_duration_is_written_in_plain_portuguese(): void
    {
        $this->assertSame('—', OnboardingMetricas::duracao(null));
        $this->assertSame('45 min', OnboardingMetricas::duracao(45));
        $this->assertSame('3 h 20 min', OnboardingMetricas::duracao(200));
        $this->assertSame('2 h', OnboardingMetricas::duracao(120));
        $this->assertSame('1 dia 2 h', OnboardingMetricas::duracao(1560));
        $this->assertSame('3 dias', OnboardingMetricas::duracao(4320));
    }

    public function test_command_prints_the_numbers(): void
    {
        $this->cenario(3, 180);

        $this->artisan('onboarding:metricas --dias=30')->expectsOutputToContain('Concluíram em até 24 h')->expectsOutputToContain('Tempo mediano até concluir: 3 h')->assertSuccessful();
    }

    public function test_page_is_only_for_super_admin(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x')]);
        config(['oravel.super_admins' => [$super->email]]);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $this->actingAs($super);
        $this->cenario(3, 180);
        Livewire::test(OnboardingMetricasPage::class)->assertSuccessful()->assertSee('Concluíram em até 24 h')->assertSee('Serviços');
        $this->assertTrue(OnboardingMetricasPage::canAccess());

        $comum = User::create(['name' => 'Comum', 'email' => 'comum-'.uniqid().'@x.com', 'password' => bcrypt('x')]);
        $this->actingAs($comum);
        $this->assertFalse(OnboardingMetricasPage::canAccess());
    }
}
