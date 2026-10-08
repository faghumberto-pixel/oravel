<?php

namespace Tests\Feature;

use App\Filament\Pages\PropostaComercialKanban;
use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaMulta;
use App\Models\FrotaPneu;
use App\Models\FrotaSinistro;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\PendenciasFrotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Dados de demonstração do cliente Oravel: criam tudo pelas regras reais, não tocam em outro cliente e saem com --remover. */
class DemoPopularOravelTest extends TestCase
{
    use DatabaseTransactions;

    private function oravel(): array
    {
        $plano = Plan::create(['name' => 'PREMIUM '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'Oravel', 'slug' => 'oravel', 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin Oravel', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin, $plano];
    }

    public function test_popula_a_frota_e_as_propostas_liga_os_modulos_e_nao_toca_em_outro_cliente(): void
    {
        [$oravel, $admin, $plano] = $this->oravel();
        $outroPlano = Plan::create(['name' => 'Outro '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $outro = Tenant::create(['name' => 'Cliente Real', 'slug' => 'cliente-real-'.uniqid(), 'plan_id' => $outroPlano->id, 'status' => 'active']);
        $antes = [Asset::withoutGlobalScopes()->where('tenant_id', $outro->id)->count(), $outroPlano->fresh()->features];

        $this->artisan('demo:popular-oravel')->assertSuccessful();

        // Frota
        $ativos = Asset::withoutGlobalScopes()->where('tenant_id', $oravel->id)->where('tag', 'like', 'DEMO-%');
        $this->assertSame(6, (clone $ativos)->count());                                            // 4 veículos + 2 máquinas
        $this->assertSame(2, (clone $ativos)->where('grupo', 'maquina')->whereNotNull('placa')->count());
        $v1 = Asset::withoutGlobalScopes()->where('tag', 'DEMO-V1')->first();
        $this->assertSame('bau', $v1->carroceria_tipo);
        $this->assertSame(3, $v1->quantidade_eixos);
        $this->assertGreaterThan(100000, (int) $v1->odometro_atual);                                 // o odômetro subiu pelos abastecimentos
        $this->assertNotNull(Asset::withoutGlobalScopes()->where('tag', 'DEMO-V4')->first()->baixaVigente());   // veículo vendido
        $this->assertSame(3, FleetDriver::withoutGlobalScopes()->where('tenant_id', $oravel->id)->count());
        $this->assertSame(9, FrotaPneu::withoutGlobalScopes()->where('tenant_id', $oravel->id)->count());
        $this->assertSame(3, FrotaMulta::withoutGlobalScopes()->where('tenant_id', $oravel->id)->count());
        $sinistro = FrotaSinistro::withoutGlobalScopes()->where('tenant_id', $oravel->id)->first();
        $this->assertTrue($sinistro->parado());
        $this->assertNotNull($sinistro->ordem_servico_id);

        // As pendências aparecem em todas as categorias que a demonstração quer mostrar
        $this->actingAs($admin);
        $categorias = app(PendenciasFrotaService::class)->todas()->pluck('categoria')->unique()->sort()->values()->all();
        foreach (['bateria', 'checklist', 'chave', 'cnh', 'consumo', 'documento', 'estoque', 'kit', 'lavagem', 'multa', 'oleo', 'pneu', 'revisao', 'saida', 'sinistro'] as $esperada) {
            $this->assertContains($esperada, $categorias, "A demonstração deveria gerar pendência de {$esperada}.");
        }

        // Propostas: uma em cada etapa do Kanban
        $colunas = (new PropostaComercialKanban)->getRecords();
        foreach (PropostaComercial::statusLabels() as $status => $rotulo) {
            $this->assertNotEmpty($colunas->get($status), "O Kanban deveria ter proposta em \"{$rotulo}\".");
        }
        $aprovada = PropostaComercial::withoutGlobalScopes()->where('status', PropostaComercial::STATUS_APROVADA_INTERNA)->first();
        $this->assertStringStartsWith('https://wa.me/5519999332615?text=', $aprovada->linkWhatsapp());

        // Módulos ligados só no contrato do Oravel; o outro cliente ficou como estava
        $features = $plano->fresh()->features;
        $this->assertTrue($features['tabela_frota_multas']);
        $this->assertTrue($features['tabela_proposta_comercial']);
        $this->assertSame($antes, [Asset::withoutGlobalScopes()->where('tenant_id', $outro->id)->count(), $outroPlano->fresh()->features]);
    }

    public function test_nao_duplica_sem_force_e_remover_apaga_so_a_demonstracao(): void
    {
        [$oravel, $admin] = $this->oravel();
        $real = Asset::create(['tenant_id' => $oravel->id, 'name' => 'Gerador real do cliente', 'tag' => 'REAL-1', 'patrimonio' => 'REAL-1', 'status' => Asset::STATUS_DISPONIVEL]);
        $propostaReal = PropostaComercial::create(['tenant_id' => $oravel->id, 'seller_user_id' => $admin->id, 'terms' => 'Proposta de verdade']);
        $clienteReal = Client::create(['tenant_id' => $oravel->id, 'name' => 'Cliente de verdade', 'email' => 'real@cliente.test']);

        $this->artisan('demo:popular-oravel')->assertSuccessful();
        $this->artisan('demo:popular-oravel')->assertFailed();                                       // já existe
        $this->assertSame(6, Asset::withoutGlobalScopes()->where('tenant_id', $oravel->id)->where('tag', 'like', 'DEMO-%')->count());

        $this->artisan('demo:popular-oravel', ['--remover' => true])->assertSuccessful();

        $this->assertSame(0, Asset::withoutGlobalScopes()->where('tenant_id', $oravel->id)->where('tag', 'like', 'DEMO-%')->count());
        $this->assertSame(0, FrotaPneu::withoutGlobalScopes()->where('tenant_id', $oravel->id)->count());
        $this->assertSame(0, FleetDriver::withoutGlobalScopes()->where('tenant_id', $oravel->id)->count());
        $this->assertSame(0, Client::withoutGlobalScopes()->where('tenant_id', $oravel->id)->where('name', 'like', 'Demo — %')->count());
        $this->assertSame(1, PropostaComercial::withoutGlobalScopes()->where('tenant_id', $oravel->id)->count());   // só a de verdade
        $this->assertNotNull($real->fresh());
        $this->assertNotNull($propostaReal->fresh());
        $this->assertNotNull($clienteReal->fresh());
    }

    public function test_sem_o_cliente_oravel_o_comando_recusa(): void
    {
        $this->artisan('demo:popular-oravel')->assertFailed();
    }

    public function test_cria_o_usuario_de_teste_do_cliente_sem_ser_administrador_da_plataforma(): void
    {
        [$oravel] = $this->oravel();
        config(['oravel.super_admins' => ['super@oravel.test']]);

        $this->artisan('demo:popular-oravel', ['--criar-usuario' => true])
            ->expectsOutputToContain('demo.oravel@oravel.test')
            ->assertSuccessful();

        $u = User::where('email', 'demo.oravel@oravel.test')->first();
        $this->assertSame($oravel->id, $u->tenant_id);
        $this->assertFalse($u->isSuperAdmin());
        $this->assertTrue($u->isAdmin());
        $this->assertTrue((bool) $u->is_approved);
        $this->assertNotNull($u->email_verified_at);

        // Esse usuário abre a lista de ativos e vê os dados de demonstração, e só do próprio cliente.
        $this->actingAs($u);
        $this->assertSame(6, Asset::count());
        $this->get('/admin/assets')->assertOk();

        $this->artisan('demo:popular-oravel', ['--remover' => true])->assertSuccessful();
        $this->assertNull(User::where('email', 'demo.oravel@oravel.test')->first());
    }
}
