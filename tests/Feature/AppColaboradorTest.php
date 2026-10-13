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
use Illuminate\Support\Facades\Route;
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
        // Esta página não carrega Alpine/Livewire: nada de x-data/@click/x-show, senão o botão fica parado (bug de 12/10).
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('x-show', $html);
        $this->assertStringContainsString("getElementById('qr-abrir')", $html);
        $this->assertStringNotContainsString('não lê QR por aqui', $html);
        $this->assertFileExists(public_path('vendor/jsqr.js'));
        $this->assertGreaterThan(100000, filesize(public_path('vendor/jsqr.js')));
    }

    public function test_app_forca_https_atras_do_cloudflare_para_a_camera_funcionar(): void
    {
        // Sem cabeçalho do Cloudflare (DEV/local) nada muda.
        $this->celular()->get('/colaborador/entrar')->assertOk();

        // Visitante chegou por http: vai para https (câmera e push exigem página segura).
        $this->celular()->withHeaders(['CF-Visitor' => '{"scheme":"http"}'])->get('/colaborador/entrar')
            ->assertStatus(301)->assertRedirect('https://localhost/colaborador/entrar');

        // Visitante em https: links e redirecionamentos saem em https (antes saíam http e a câmera sumia).
        $user = $this->usuario(true);
        $this->celular()->withHeaders(['CF-Visitor' => '{"scheme":"https"}'])
            ->post('/colaborador/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('https://localhost/colaborador');
    }

    public function test_app_abre_sem_internet_service_worker_tela_offline_e_cache_por_tela(): void
    {
        $user = $this->usuario(true);

        // A tela "sem conexão" abre sem login (o service worker a guarda na instalação).
        $this->celular()->get('/colaborador/offline')->assertOk()->assertSee('Sem conexão')->assertSee('registro de ponto continua funcionando', false);

        // Todas as telas do app registram o service worker.
        $this->celular()->get('/colaborador/entrar')->assertOk()->assertSee('serviceWorker.register', false);
        $inicio = $this->celular()->actingAs($user)->get('/colaborador')->assertOk()->getContent();
        $this->assertStringContainsString('serviceWorker.register', $inicio);
        // O início guarda as outras telas no aparelho e limpa tudo ao sair da conta.
        $this->assertStringContainsString('navigator.serviceWorker.ready', $inicio);
        $this->assertStringContainsString('/colaborador/ponto', $inicio);
        $this->assertStringContainsString('oravel-colab-paginas-', $inicio);

        // O service worker: páginas rede-primeiro com cópia local, nunca mexe em POST/livewire/api, e mantém o push.
        $sw = $this->celular()->get('/colaborador/colaborador-sw.js')->assertOk()->getContent();
        foreach (['caches.open(PAGINAS)', "req.method !== 'GET'", 'livewire', 'OFFLINE', "addEventListener('push'", 'showNotification', "event.data === 'limpar'"] as $trecho) {
            $this->assertStringContainsString($trecho, $sw, $trecho);
        }
    }

    public function test_nenhuma_rota_do_app_colide_com_arquivo_fisico_da_pasta_public(): void
    {
        // No subdomínio as rotas ficam na raiz: se existir public/<mesmo caminho>, o nginx serve o arquivo e o Laravel
        // nem é chamado (foi o que aconteceu com /sw.js em 12/10). Em DEV elas ficam sob /colaborador.
        $colisoes = [];
        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with((string) $rota->getName(), 'colab.')) {
                continue;
            }
            $caminho = trim(preg_replace('#^colaborador(/|$)#', '', $rota->uri()), '/');
            if ($caminho !== '' && ! str_contains($caminho, '{') && file_exists(public_path($caminho))) {
                $colisoes[] = $caminho;
            }
        }

        $this->assertSame([], $colisoes, 'Rotas do app colidem com arquivos de public/: '.implode(', ', $colisoes));
    }

    public function test_inicio_separa_as_funcoes_por_area_na_ordem_departamento_pessoal_logistica_manutencao_ativos(): void
    {
        $user = $this->usuario(true, [], ['tabela_assets', 'tabela_time_clocks', 'tabela_maintenance_orders', 'tabela_frota_saidas_veiculo', 'modulo_app_colaborador']);

        $html = $this->celular()->actingAs($user)->get('/colaborador')->assertOk()->getContent();

        $posicoes = collect(['Departamento Pessoal', 'Logística', 'Manutenção', 'Ativos'])->map(fn ($area) => mb_stripos($html, '>'.$area.'<'));
        $this->assertNotContains(false, $posicoes->all(), 'todas as áreas aparecem como título');
        $this->assertSame($posicoes->sort()->values()->all(), $posicoes->values()->all(), 'ordem das áreas no início');
        $this->assertGreaterThan(mb_stripos($html, '>Departamento Pessoal<'), mb_stripos($html, 'Registro de ponto'), 'o ponto fica sob Departamento Pessoal');
        $this->assertLessThan(mb_stripos($html, '>Logística<'), mb_stripos($html, 'Registro de ponto'));
        $this->assertStringNotContainsString('>Geral<', $html);
    }
}
