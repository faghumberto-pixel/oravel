<?php

namespace Tests\Feature;

use App\Console\Commands\ImportAcademyCourses;
use App\Filament\Central\Resources\CourseResource as CentralCourseResource;
use App\Filament\Resources\CourseResource as AdminCourseResource;
use App\Filament\Resources\CourseResource\Pages\ListCourses;
use App\Filament\Resources\CourseResource\Pages\ViewCourse;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Academia Oravel: conteúdo global (Central), leitura + progresso por usuário no app, módulo ligado por contrato. */
class AcademyTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(string $name, array $features = ['tabela_courses']): User
    {
        $plan = Plan::create(['name' => 'Plano '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Aluno '.$name, 'email' => 'aluno-'.uniqid().'@teste.com', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_curso', 'guard_name' => 'web']));

        return $user->fresh();
    }

    private function course(string $title, bool $published = true, int $lessons = 2): Course
    {
        $course = Course::create(['title' => $title, 'slug' => str($title)->slug().'-'.uniqid(), 'is_published' => $published]);
        foreach (range(1, $lessons) as $i) {
            Lesson::create(['course_id' => $course->id, 'title' => "Aula $i", 'position' => $i]);
        }

        return $course;
    }

    private function lessonFor(Course $course, string $title, ?string $feature): Lesson
    {
        return Lesson::create(['course_id' => $course->id, 'title' => $title, 'feature_key' => $feature, 'position' => 9]);
    }

    private function asAdminPanel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_client_sees_only_published_courses_with_their_progress(): void
    {
        $user = $this->tenantUser('Cliente A');
        $publicado = $this->course('Primeiros passos');
        $this->course('Rascunho secreto', false);
        LessonProgress::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'lesson_id' => $publicado->lessons()->first()->id, 'completed_at' => now()]);

        $this->asAdminPanel($user);

        Livewire::test(ListCourses::class)
            ->assertSee('Primeiros passos')
            ->assertSee('1 de 2')
            ->assertDontSee('Rascunho secreto');
    }

    public function test_completing_a_lesson_saves_progress_for_the_tenant_and_toggles_off(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course('Primeiros passos');
        $lesson = $course->lessons()->first();
        $this->asAdminPanel($user);

        $page = Livewire::test(ViewCourse::class, ['record' => $course->getKey()])
            ->call('toggleDone', $lesson->id);

        $row = LessonProgress::withoutGlobalScopes()->where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame($user->tenant_id, $row->tenant_id);
        $page->assertSee('1 de 2 aulas (50%)');

        $page->call('toggleDone', $lesson->id);
        $this->assertSame(0, LessonProgress::withoutGlobalScopes()->count());
    }

    public function test_progress_is_isolated_between_clients(): void
    {
        $a = $this->tenantUser('Cliente A');
        $b = $this->tenantUser('Cliente B');
        $course = $this->course('Primeiros passos');
        LessonProgress::create(['tenant_id' => $a->tenant_id, 'user_id' => $a->id, 'lesson_id' => $course->lessons()->first()->id, 'completed_at' => now()]);

        $this->asAdminPanel($b);

        $this->assertSame(0, LessonProgress::count()); // escopo de tenant: B nem enxerga a linha de A
        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])->assertSee('0 de 2 aulas (0%)');
    }

    public function test_module_off_in_the_contract_hides_the_academy(): void
    {
        $semModulo = $this->tenantUser('Cliente Sem', []);
        $this->asAdminPanel($semModulo);
        $this->assertFalse(AdminCourseResource::canViewAny());

        $comModulo = $this->tenantUser('Cliente Com');
        $this->asAdminPanel($comModulo);
        $this->assertTrue(AdminCourseResource::canViewAny());
    }

    public function test_client_cannot_open_a_draft_course(): void
    {
        $user = $this->tenantUser('Cliente A');
        $rascunho = $this->course('Rascunho secreto', false);
        $this->asAdminPanel($user);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ViewCourse::class, ['record' => $rascunho->getKey()]);
    }

    public function test_embed_url_only_accepts_youtube_and_vimeo(): void
    {
        $l = new Lesson;
        $l->video_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $l->embedUrl());
        $l->video_url = 'https://youtu.be/dQw4w9WgXcQ';
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $l->embedUrl());
        $l->video_url = 'https://vimeo.com/123456789';
        $this->assertSame('https://player.vimeo.com/video/123456789', $l->embedUrl());
        $l->video_url = 'https://evil.example.com/watch?v=dQw4w9WgXcQ';
        $this->assertNull($l->embedUrl());
        $l->video_url = 'javascript:alert(1)';
        $this->assertNull($l->embedUrl());
    }

    public function test_import_is_idempotent_skips_internal_group_and_keeps_manual_edits(): void
    {
        $nav = tempnam(sys_get_temp_dir(), 'nav').'.js';
        file_put_contents($nav, <<<'JS'
const ORAVEL_ACADEMY_NAV = [
  {
    group: "Comece Aqui",
    items: [
      { title: "Bem-vindo", href: "/index.html" },
      { title: "Visão Geral", href: "/visao-geral.html" },
    ],
  },
  {
    group: "Painel Central (Time Oravel)",
    items: [
      { title: "Tenants", href: "/painel-central/tenants.html" },
    ],
  },
];
JS);

        $this->artisan('academy:import', ['path' => $nav])->assertExitCode(0);
        $this->assertSame(1, Course::count());
        $course = Course::first();
        $this->assertFalse($course->is_published); // padrão: rascunho
        $this->assertSame(2, $course->lessons()->count());
        $this->assertSame('https://academy.oravel.com.br/index.html', $course->lessons()->first()->page_url);

        $course->lessons()->first()->update(['video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Editada à mão']);
        $course->update(['is_published' => true]);

        $this->artisan('academy:import', ['path' => $nav])->assertExitCode(0);
        $this->assertSame(1, Course::count());
        $this->assertSame(2, Lesson::count());
        $this->assertTrue($course->fresh()->is_published); // não despublica/republica
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $course->lessons()->first()->video_url);
    }

    public function test_central_lists_courses_with_how_many_clients_studied(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $a = $this->tenantUser('Cliente A');
        $b = $this->tenantUser('Cliente B');
        $course = $this->course('Primeiros passos');
        $lesson = $course->lessons()->first();
        LessonProgress::create(['tenant_id' => $a->tenant_id, 'user_id' => $a->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        LessonProgress::create(['tenant_id' => $b->tenant_id, 'user_id' => $b->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(CentralCourseResource\Pages\ListCourses::class)
            ->assertSee('Primeiros passos')
            ->assertTableColumnStateSet('clientes', 2, $course)
            ->assertTableColumnStateSet('usuarios', 2, $course);
    }

    public function test_client_only_sees_lessons_and_courses_of_the_modules_in_its_contract(): void
    {
        $comAtivos = $this->tenantUser('Cliente Ativos', ['tabela_courses', 'tabela_assets']);
        $soAcademia = $this->tenantUser('Cliente Basico', ['tabela_courses']);

        $geral = Course::create(['title' => 'Comece aqui', 'slug' => 'comece-'.uniqid(), 'is_published' => true]);
        $this->lessonFor($geral, 'Boas-vindas', null);
        $this->lessonFor($geral, 'Aula de Ativos', 'tabela_assets');

        $manut = Course::create(['title' => 'Manutenção avançada', 'slug' => 'manut-'.uniqid(), 'is_published' => true]);
        $this->lessonFor($manut, 'Aula de OS', 'tabela_maintenance_orders');

        // cliente com o módulo Ativos: vê o curso geral inteiro (2 aulas), mas não o de manutenção
        $this->asAdminPanel($comAtivos);
        Livewire::test(ListCourses::class)
            ->assertSee('Comece aqui')->assertSee('0 de 2')
            ->assertDontSee('Manutenção avançada');
        Livewire::test(ViewCourse::class, ['record' => $geral->getKey()])
            ->assertSee('Aula de Ativos')->assertSee('Boas-vindas');

        // cliente só com a Academia: o curso geral aparece, mas só com a aula sem módulo
        $this->asAdminPanel($soAcademia);
        Livewire::test(ListCourses::class)->assertSee('Comece aqui')->assertSee('0 de 1')->assertDontSee('Manutenção avançada');
        Livewire::test(ViewCourse::class, ['record' => $geral->getKey()])
            ->assertSee('Boas-vindas')->assertDontSee('Aula de Ativos');
    }

    public function test_client_cannot_complete_or_open_a_course_outside_its_contract(): void
    {
        $user = $this->tenantUser('Cliente Basico', ['tabela_courses']);
        $manut = Course::create(['title' => 'Manutenção avançada', 'slug' => 'manut-'.uniqid(), 'is_published' => true]);
        $fechada = $this->lessonFor($manut, 'Aula de OS', 'tabela_maintenance_orders');
        $geral = Course::create(['title' => 'Comece aqui', 'slug' => 'comece-'.uniqid(), 'is_published' => true]);
        $this->lessonFor($geral, 'Boas-vindas', null);
        $this->asAdminPanel($user);

        // abrir o curso fechado pela URL: não existe pra esse cliente
        try {
            Livewire::test(ViewCourse::class, ['record' => $manut->getKey()]);
            $this->fail('abriu um curso fora do contrato');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        // concluir aula fechada via curso liberado: negado
        try {
            Livewire::test(ViewCourse::class, ['record' => $geral->getKey()])->call('toggleDone', $fechada->id);
            $this->fail('concluiu aula fora do contrato');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, LessonProgress::withoutGlobalScopes()->count());
        }
    }

    public function test_every_module_mapped_by_the_importer_exists_in_the_registry(): void
    {
        $map = (new \ReflectionClassConstant(ImportAcademyCourses::class, 'FEATURES'))->getValue();
        $known = array_keys(Plan::getAvailableFeaturesOptions());

        $this->assertSame([], array_values(array_diff(array_unique($map), $known)));
    }

    public function test_a_completed_course_stays_available_to_review(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course('Primeiros passos');
        foreach ($course->lessons as $lesson) {
            LessonProgress::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        }
        $lesson = $course->lessons()->first();
        $lesson->update(['body' => '<p>Texto da aula</p>']);
        $this->asAdminPanel($user);

        // continua na lista, agora com o botão "Rever"
        Livewire::test(ListCourses::class)->assertSee('Primeiros passos')->assertSee('2 de 2')->assertSee('Rever');

        // abre normalmente, mostra o aviso e deixa abrir o conteúdo de qualquer aula
        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])
            ->assertSee('Curso concluído')
            ->assertSee('2 de 2 aulas (100%)')
            ->call('open', $lesson->id)
            ->assertSee('Texto da aula');
    }

    public function test_migration_turns_the_academy_on_for_existing_contracts_without_overriding_choices(): void
    {
        $mk = fn (string $n, array $f) => Plan::create(['name' => $n, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $f]);
        $lista = $mk('Contrato lista', ['tabela_assets']);
        $mapa = $mk('Contrato mapa', ['tabela_assets' => true]);
        $recusou = $mk('Contrato recusou', ['tabela_assets' => true, 'tabela_courses' => false]);

        (require database_path('migrations/2026_10_02_130000_enable_academy_on_existing_plans.php'))->up();

        $this->assertTrue($lista->fresh()->hasFeature('tabela_courses'));
        $this->assertTrue($lista->fresh()->hasFeature('tabela_assets'));
        $this->assertTrue($mapa->fresh()->hasFeature('tabela_courses'));
        $this->assertFalse($recusou->fresh()->hasFeature('tabela_courses')); // escolha explícita preservada
    }
}
