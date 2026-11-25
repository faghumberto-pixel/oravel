<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceEquipamento;
use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Models\IntelligenceIndicator;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pacotes;
use App\Support\Tour\Tour;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IntelligenceExemploTest extends TestCase
{
    use DatabaseTransactions;

    private function trial(): IntelligenceTrial
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => collect(Pacotes::features('intelligence'))->mapWithKeys(fn ($c) => [$c => true])->all()]); // como o plano do teste de verdade
        $t = Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'l-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial']);
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);

        return IntelligenceTrial::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'nome' => 'Maria', 'empresa' => $t->name, 'whatsapp' => '+5519'.random_int(900000000, 999999999), 'status' => 'trial',
        ]);
    }

    private function importReal(IntelligenceTrial $trial): IntelligenceImport
    {
        $imp = IntelligenceImport::withoutGlobalScopes()->create(['tenant_id' => $trial->tenant_id, 'origem' => 'arquivo', 'status' => 'concluida']);
        IntelligenceSnapshot::withoutGlobalScopes()->create(['tenant_id' => $trial->tenant_id, 'import_id' => $imp->id, 'data_referencia' => now()->toDateString(), 'dados' => ['x' => 1], 'hash' => hash('sha256', uniqid())]);

        return $imp;
    }

    public function test_primeira_abertura_gera_exemplo_marcado_e_nao_inicia_o_relogio_do_teste(): void
    {
        $trial = $this->trial();
        app(DadosDeExemplo::class)->aoAbrir($trial);

        $imports = IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->get();
        $this->assertCount(3, $imports);
        $this->assertSame(['demonstracao'], $imports->pluck('origem')->unique()->all());
        $this->assertTrue(DadosDeExemplo::soExemplo($trial->tenant_id));
        $this->assertGreaterThan(0, IntelligenceIndicator::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count());
        $this->assertGreaterThan(0, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count());

        $trial->refresh();
        $this->assertNotNull($trial->exemplo_oferecido_em);
        $this->assertNull($trial->trial_started_at, 'exemplo nunca inicia os 30 dias do teste');
        $this->assertNull($trial->last_upload_at);

        // datas relativas: o exemplo termina no mês passado
        $ultima = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->max('data_referencia');
        $this->assertSame(now()->subMonthNoOverflow()->endOfMonth()->toDateString(), substr((string) $ultima, 0, 10));
    }

    public function test_abrir_de_novo_nao_duplica_nem_recria_depois_de_limpar(): void
    {
        $trial = $this->trial();
        $servico = app(DadosDeExemplo::class);
        $servico->aoAbrir($trial);
        $servico->aoAbrir($trial->fresh());
        $this->assertSame(3, IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count());

        $servico->limpar($trial->tenant_id);
        $servico->aoAbrir($trial->fresh()); // já foi oferecido: não volta sozinho
        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id));
    }

    public function test_limpar_apaga_so_o_exemplo_em_cascata_e_preserva_o_dado_real(): void
    {
        $trial = $this->trial();
        $real = $this->importReal($trial);
        $servico = app(DadosDeExemplo::class);
        $servico->gerar($trial->tenant);
        $this->assertFalse(DadosDeExemplo::soExemplo($trial->tenant_id), 'com dado real não é "só exemplo"');

        $this->assertSame(3, $servico->limpar($trial->tenant));

        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id));
        $this->assertSame(0, IntelligenceIndicator::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count());
        $this->assertSame(0, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count());
        $this->assertNotNull(IntelligenceImport::withoutGlobalScopes()->find($real->id));
        $this->assertSame(1, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count());
    }

    public function test_pode_pedir_o_exemplo_de_volta_so_sem_dado_proprio_e_dentro_do_prazo(): void
    {
        $trial = $this->trial();
        $servico = app(DadosDeExemplo::class);
        $servico->aoAbrir($trial);
        $servico->limpar($trial->tenant_id);
        $this->assertTrue(DadosDeExemplo::podeGerar($trial->fresh()));

        $this->actingAs($trial->user)->post(route('intelligence.trial.exemplo.gerar'))->assertRedirect();
        $this->assertTrue(DadosDeExemplo::existe($trial->tenant_id));

        $servico->limpar($trial->tenant_id);
        $this->importReal($trial);
        $this->assertFalse(DadosDeExemplo::podeGerar($trial->fresh()), 'com dado próprio não volta o exemplo');
    }

    public function test_depois_de_30_dias_o_exemplo_some_de_vez_e_nao_volta(): void
    {
        $trial = $this->trial();
        app(DadosDeExemplo::class)->aoAbrir($trial);
        $this->assertSame(30, DadosDeExemplo::diasRestantes($trial->fresh()));

        $this->travelTo(now()->addDays(31));
        $this->assertTrue(DadosDeExemplo::expirou($trial->fresh()));
        $this->artisan('intelligence:exemplo-expirar')->assertSuccessful();

        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id));
        $this->assertFalse(DadosDeExemplo::podeGerar($trial->fresh()));
        $this->actingAs($trial->user)->post(route('intelligence.trial.exemplo.gerar'));
        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id), 'passado o prazo, o botão não traz o exemplo de volta');
    }

    public function test_exemplo_que_sobrou_e_apagado_quando_o_cliente_entra_depois_do_prazo(): void
    {
        $trial = $this->trial();
        $servico = app(DadosDeExemplo::class);
        $servico->aoAbrir($trial);
        $this->travelTo(now()->addDays(31));

        $servico->aoAbrir($trial->fresh());

        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id));
    }

    public function test_faixa_avisa_que_e_demonstracao_e_o_botao_limpa(): void
    {
        $trial = $this->trial();
        app(DadosDeExemplo::class)->aoAbrir($trial);
        $this->actingAs($trial->user);

        $html = view('filament.intelligence-trial-banner')->render();
        $this->assertStringContainsString('DEMONSTRAÇÃO', $html);
        $this->assertStringContainsString('Limpar dados de exemplo', $html);

        $this->post(route('intelligence.trial.exemplo.limpar'))->assertRedirect();
        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id));
        $this->assertStringContainsString('Ver dados de exemplo', view('filament.intelligence-trial-banner')->render());
    }

    public function test_primeiro_pop_up_avisa_que_os_dados_sao_de_demonstracao(): void
    {
        $trial = $this->trial();
        $passo = collect(config('tour.tours.intelligence.passos'))->firstWhere('chave', 'importacoes');
        $tour = (new \ReflectionClass(Tour::class))->newInstanceWithoutConstructor();
        $prop = fn (string $n, $v) => tap(new \ReflectionProperty($tour, $n), fn ($p) => $p->setValue($tour, $v));
        $prop('tenant', $trial->tenant);

        $this->assertStringNotContainsString('DEMONSTRAÇÃO', (string) $tour->notaTrial($passo));
        app(DadosDeExemplo::class)->gerar($trial->tenant);
        $this->assertStringStartsWith('ATENÇÃO', (string) $tour->notaTrial($passo));
        $this->assertStringContainsString('DEMONSTRAÇÃO', (string) $tour->notaTrial($passo));
    }
}
