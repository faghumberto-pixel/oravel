<?php

namespace Tests\Feature;

use App\Filament\Pages\PrimeirosPassos;
use App\Filament\Resources\PropostaComercialResource;
use App\Models\Client;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppProfile;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Tela de Primeiros passos: conteúdo, isolamento por empresa, página inicial e ajuda por WhatsApp. */
class OnboardingTelaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(string $pacote = 'servicos'): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true]); // contrato em vigor: os módulos do pacote valem para a empresa
        $t = Tenant::create(['name' => 'Empresa Teste '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Carlos Souza', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    public function test_screen_greets_by_first_name_with_steps_progress_and_time(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PrimeirosPassos::class)->assertSuccessful()
            ->assertSee('Bem-vindo, Carlos')->assertSee('Faltam 3 passos para enviar sua primeira proposta. Leva cerca de 25 minutos.')
            ->assertSee('Passo 1 de 3')->assertSee('Importe seus clientes')->assertSee('Crie sua primeira proposta')->assertSee('Acompanhe o aceite')
            ->assertSee('Baixar planilha modelo')->assertSee('Travou em algum passo?')->assertSee('A proposta aceita vira contrato de serviço');
    }

    public function test_screen_moves_on_by_itself_and_ends_with_a_closing_message(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        Client::create(['tenant_id' => $u->tenant_id, 'name' => 'Primeiro cliente']);

        Livewire::test(PrimeirosPassos::class)->assertSee('Faltam 2 passos')->assertSee('Passo 2 de 3')->assertDontSee('Baixar planilha modelo')
            ->assertSee('Criar proposta')->assertSee(PropostaComercialResource::getUrl('create'), false);
    }

    public function test_help_link_carries_company_and_current_step(): void
    {
        $u = $this->admin();
        $this->actingAs($u);

        $pagina = Livewire::test(PrimeirosPassos::class)->instance();
        $link = $pagina->linkDeAjuda($pagina->getEstado());

        $this->assertStringStartsWith('https://wa.me/5519999332615?text=', $link);
        $texto = urldecode(substr($link, strlen('https://wa.me/5519999332615?text=')));
        $this->assertStringContainsString($u->tenant->name, $texto);
        $this->assertStringContainsString('passo 1 (Importe seus clientes)', $texto);
    }

    public function test_it_is_the_home_while_pending_and_goes_away_when_dismissed(): void
    {
        $u = $this->admin();
        $this->actingAs($u);

        $this->assertSame(PrimeirosPassos::getUrl(), AppProfile::homeUrl($u, AppProfile::ADMIN));
        $this->assertTrue(PrimeirosPassos::shouldRegisterNavigation());

        Livewire::test(PrimeirosPassos::class)->call('dispensar');

        $this->assertNotSame(PrimeirosPassos::getUrl(), AppProfile::homeUrl($u, AppProfile::ADMIN));
        $this->assertTrue(PrimeirosPassos::shouldRegisterNavigation(), 'o menu continua disponível para voltar ao guia');
    }

    public function test_solutions_without_onboarding_and_non_admins_do_not_see_it(): void
    {
        $this->actingAs($this->admin('estoque'));
        $this->assertFalse(PrimeirosPassos::canAccess());

        $admin = $this->admin();
        $comum = User::create(['name' => 'Func', 'email' => 'f-'.uniqid().'@x.com', 'password' => bcrypt('x'), 'tenant_id' => $admin->tenant_id]);
        $comum->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $this->actingAs($comum);
        $this->assertFalse(PrimeirosPassos::canAccess());
    }

    public function test_each_company_sees_only_its_own_progress(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        Client::create(['tenant_id' => $a->tenant_id, 'name' => 'Cliente da A']);

        $this->actingAs($b);
        Livewire::test(PrimeirosPassos::class)->assertSee('Faltam 3 passos');

        $this->actingAs($a);
        Livewire::test(PrimeirosPassos::class)->assertSee('Faltam 2 passos');
        $this->assertTrue(app(OnboardingService::class)->estado($a->tenant)['passos'][0]['concluido']);
    }

    public function test_lesson_button_only_shows_when_the_course_is_published_and_available(): void
    {
        $this->actingAs($this->admin());
        $curso = Course::create(['title' => 'Comece por aqui — Serviços', 'slug' => 'comece-por-aqui-servicos', 'is_published' => false, 'position' => 99]);
        Lesson::create(['course_id' => $curso->id, 'title' => 'Sua primeira proposta', 'page_url' => '/comece-por-aqui/primeira-proposta.html', 'feature_key' => 'tabela_proposta_comercial', 'position' => 1]);

        Livewire::test(PrimeirosPassos::class)->assertDontSee('Assistir aula de 5 min');

        $curso->update(['is_published' => true]);
        Livewire::test(PrimeirosPassos::class)->assertSee('Assistir aula de 5 min')->assertSee(route('academy.course', 'comece-por-aqui-servicos'), false);
    }
}
