<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Colaborador\Modulos;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** App do colaborador: link próprio, só celular, login com o usuário do sistema, funções por perfil. */
class AppColaboradorTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    private function usuario(bool $admin, array $extra = [], array $features = ['tabela_assets', 'tabela_time_clocks', 'modulo_app_colaborador']): User
    {
        $plan = Plan::create(['name' => 'Plano Colab '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'Locadora Colab', 'slug' => 'colab-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Joana', 'email' => 'colab-'.uniqid().'@oravel.com.br', 'password' => bcrypt('segredo123'), 'tenant_id' => $tenant->id]);
        $user->forceFill(array_merge(['email_verified_at' => now(), 'is_approved' => true], $extra))->save();
        if ($admin) {
            $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        }

        return $user->refresh();
    }

    private function celular(): self
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR]);
    }

    public function test_no_computador_mostra_qr_e_nao_o_app(): void
    {
        $this->withHeaders(['User-Agent' => self::DESKTOP])->get('/colaborador')
            ->assertOk()->assertSee('Abra no celular')->assertSee('<svg', false)->assertDontSee('Entrar');
    }

    public function test_celular_sem_login_vai_para_a_tela_de_entrada(): void
    {
        $this->celular()->get('/colaborador')->assertRedirect(route('colab.login'));
        $this->celular()->get('/colaborador/entrar')->assertOk()->assertSee('Entre com o seu usuário do sistema');
    }

    public function test_login_com_usuario_do_sistema_abre_o_inicio(): void
    {
        $user = $this->usuario(true);

        $this->celular()->post('/colaborador/entrar', ['email' => $user->email, 'password' => 'segredo123'])->assertRedirect(route('colab.home'));
        $this->celular()->actingAs($user)->get('/colaborador')->assertOk()->assertSee('Registro de ponto')->assertSee('Dossiê do equipamento');
    }

    public function test_senha_errada_e_conta_nao_aprovada_nao_entram(): void
    {
        $user = $this->usuario(true);
        $this->celular()->from('/colaborador/entrar')->post('/colaborador/entrar', ['email' => $user->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $pendente = $this->usuario(true, ['is_approved' => false]);
        $this->celular()->post('/colaborador/entrar', ['email' => $pendente->email, 'password' => 'segredo123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_funcoes_sao_separadas_por_perfil(): void
    {
        $gestor = $this->usuario(true);
        $comum = $this->usuario(false);

        $chavesGestor = collect(Modulos::para($gestor))->pluck('chave')->all();
        $this->assertContains('dossie', $chavesGestor);
        $this->assertContains('escanear', $chavesGestor);

        // Sem perfil de técnico/gestão, não enxerga as funções de equipamento mesmo que a permissão deixasse.
        $chavesComum = collect(Modulos::para($comum))->pluck('chave')->all();
        $this->assertNotContains('dossie', $chavesComum);
        $this->assertNotContains('escanear', $chavesComum);
    }

    public function test_dossie_dentro_do_app_nao_leva_a_links_do_painel(): void
    {
        $user = $this->usuario(true);
        $asset = Asset::create(['tenant_id' => $user->tenant_id, 'name' => 'Guindaste App', 'patrimonio' => 'PAT-APP-1', 'status' => 'disponivel', 'is_vehicle' => true, 'placa' => 'ABC1D23']);

        $html = $this->celular()->actingAs($user)->get('/colaborador/dossie/'.$asset->id)->assertOk()->assertSee('Guindaste App')->getContent();

        $this->assertStringNotContainsString('/admin/frota/', $html);
        $this->assertStringNotContainsString('/dossie-pdf', $html);

        $this->celular()->actingAs($user)->get('/colaborador/dossie?busca=PAT-APP-1')->assertOk()->assertSee('Guindaste App');
    }

    public function test_contrato_sem_o_app_nao_entra_e_aparece_na_central(): void
    {
        $semApp = $this->usuario(true, [], ['tabela_assets', 'tabela_time_clocks']);

        $this->celular()->post('/colaborador/entrar', ['email' => $semApp->email, 'password' => 'segredo123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Já logado e o contrato perde o app: a próxima tela expulsa.
        $this->celular()->actingAs($semApp)->get('/colaborador')->assertRedirect(route('colab.login'));

        $this->assertArrayHasKey(Modulos::FEATURE, Plan::getAvailableFeaturesOptions());
        $this->assertContains('App do Colaborador (celular)', ContratoResource::groupedFeatureOptions()['Configurações']);
    }

    public function test_419_na_tela_de_entrada_nao_mostra_pagina_expirada(): void
    {
        // Liga a checagem real de CSRF (nos testes ela vem desligada).
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        // Visitante com token velho: volta para a entrada com aviso.
        $this->celular()->post('/colaborador/entrar', ['email' => 'a@b.com', 'password' => 'x'])
            ->assertRedirect(route('colab.login'))->assertSessionHasErrors('email');

        // Já logado (1º toque funcionou) e o 2º chegou com token velho: vai para o início, sem erro.
        $user = $this->usuario(true);
        $this->celular()->actingAs($user)->post('/colaborador/entrar', ['email' => $user->email, 'password' => 'x'])
            ->assertRedirect(route('colab.home'));
    }

    public function test_leitor_de_qr_tem_alternativa_para_navegadores_sem_leitor_nativo(): void
    {
        $user = $this->usuario(true);

        $html = $this->celular()->actingAs($user)->get('/colaborador/escanear')->assertOk()->getContent();

        // jsQR embutido (muitos Androids não têm BarcodeDetector) e mensagem só para quem não tem câmera.
        $this->assertStringContainsString('/vendor/jsqr.js', $html);
        $this->assertStringContainsString('jsQR(', $html);
        $this->assertStringNotContainsString('não lê QR por aqui', $html);
        $this->assertFileExists(public_path('vendor/jsqr.js'));
        $this->assertGreaterThan(100000, filesize(public_path('vendor/jsqr.js')));
    }
}
