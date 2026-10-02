<?php

namespace Tests\Feature;

use App\Livewire\Academy\CoursePage;
use App\Livewire\Academy\Home;
use App\Livewire\Academy\RankingPage;
use App\Livewire\Academy\Sidebar;
use App\Livewire\Academy\TeamPage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Models\LessonQuizSubmission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AcademyCertificates;
use App\Services\AcademyOverview;
use App\Services\AcademyPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Página própria da Academia (/academia): acesso, início, barra lateral, curso, equipe e ranking. */
class AcademyPageTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name, array $features = ['tabela_courses']): Tenant
    {
        $plan = Plan::create(['name' => 'Plano '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function user(Tenant $tenant, string $name, bool $admin = false, bool $permission = true): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().'-'.uniqid().'@teste.com', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        if ($permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_curso', 'guard_name' => 'web']));
        }
        if ($admin) {
            $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        }

        return $user->fresh();
    }

    private function course(string $title, int $lessons = 2, bool $published = true): Course
    {
        $course = Course::create(['title' => $title, 'slug' => str($title)->slug().'-'.uniqid(), 'is_published' => $published]);
        foreach (range(1, $lessons) as $i) {
            Lesson::create(['course_id' => $course->id, 'title' => "$title aula $i", 'position' => $i]);
        }

        return $course;
    }

    private function finish(User $user, Course $course, int $howMany): void
    {
        foreach ($course->lessons()->limit($howMany)->get() as $lesson) {
            LessonProgress::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id], ['tenant_id' => $user->tenant_id, 'completed_at' => now()]);
            app(AcademyPoints::class)->awardRead($user, $lesson);
        }
    }

    public function test_guests_are_sent_to_login_and_access_follows_the_contract_not_a_specific_permission(): void
    {
        $this->get('/academia/inicio')->assertRedirect('/admin/login');

        $semModulo = $this->user($this->tenant('Sem Modulo', []), 'Aluno');
        $semPermissao = $this->user($this->tenant('Sem Permissao'), 'Aluno', permission: false);

        $this->actingAs($semModulo)->get('/academia/inicio')->assertForbidden();
        $this->actingAs($semPermissao)->get('/academia/inicio')->assertOk()->assertSee('Oravel')->assertSee('Voltar'); // basta ter cadastro + módulo no contrato
    }

    public function test_overview_status_and_grade_follow_the_rules(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');
        $nova = $this->course('Curso novo');
        $meio = $this->course('Curso no meio', 4);
        $fim = $this->course('Curso fim', 2);
        $this->finish($user, $meio, 1);
        $this->finish($user, $fim, 2);

        $primeira = $fim->lessons()->first();
        foreach ([0, 1, 0] as $i => $correct) {
            LessonQuestion::create(['lesson_id' => $primeira->id, 'question' => 'P'.($i + 1), 'options' => [['text' => 'a'], ['text' => 'b']], 'correct_index' => $correct]);
        }
        // prova entregue: 2 certas de 3 (a errada e a em branco valem zero) -> nota 6,7, abaixo da mínima
        LessonQuizSubmission::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'lesson_id' => $primeira->id, 'total_questions' => 3, 'correct_answers' => 2, 'delivered_at' => now()]);

        $svc = app(AcademyOverview::class);
        $courses = $svc->courses($user)->keyBy('title');

        $this->assertSame('todo', $courses['Curso novo']['status']);
        $this->assertSame('progress', $courses['Curso no meio']['status']);
        $this->assertSame(25, $courses['Curso no meio']['percent']);
        $this->assertSame('done', $courses['Curso fim']['status']);
        $this->assertSame(6.7, $courses['Curso fim']['nota']);      // 2 certas de 3 = 6,7
        $this->assertNull($courses['Curso novo']['nota']);          // sem quiz
        $this->assertNull($courses['Curso fim']['certificate']);    // nota abaixo da mínima: sem certificado

        $totals = $svc->totals($user, $courses->values());
        $this->assertSame(1, $totals['courses_done']);
        $this->assertSame(1, $totals['courses_progress']);
        $this->assertSame(1, $totals['courses_todo']);
        $this->assertSame(6.7, $totals['nota']);
    }

    public function test_home_lists_courses_by_status_with_grades_and_filters(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluna Teste');
        $this->course('Curso novo');
        $meio = $this->course('Curso no meio', 4);
        $this->finish($user, $meio, 2);
        $this->course('Rascunho invisivel', 2, false);

        $home = Livewire::actingAs($user)->test(Home::class)
            ->assertSee('Olá, Aluna')
            ->assertSee('Curso novo')->assertSee('Curso no meio')
            ->assertDontSee('Rascunho invisivel')
            ->assertSee('Estatísticas por curso');
        $this->assertCount(2, $home->viewData('courses'));

        // o filtro vale para os cartões de curso (a tabela de estatísticas continua mostrando todos)
        $this->assertSame(['Curso no meio'], $home->call('$set', 'filter', 'andamento')->viewData('courses')->pluck('title')->all());
        $this->assertSame(['Curso novo'], $home->call('$set', 'filter', 'fazer')->viewData('courses')->pluck('title')->all());
        $this->assertSame([], $home->call('$set', 'filter', 'concluidos')->viewData('courses')->all());
    }

    public function test_sidebar_groups_courses_and_refreshes_after_progress(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');
        $a = $this->course('Curso A', 2);
        $this->course('Curso B', 2);

        $side = Livewire::actingAs($user)->test(Sidebar::class)
            ->assertSee('A fazer (2)')->assertDontSee('Em andamento (')->assertSee('0%');

        $this->finish($user, $a, 1);
        $side->dispatch('academy-updated')
            ->assertSee('Em andamento (1)')->assertSee('A fazer (1)')->assertSee('50%');

        $this->finish($user->fresh(), $a->fresh(), 2);
        $side->dispatch('academy-updated')->assertSee('Concluídos (1)');
    }

    public function test_course_page_completes_lessons_gives_points_and_issues_the_certificate(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');
        $course = $this->course('Curso A', 2);
        [$l1, $l2] = $course->lessons;
        $q = LessonQuestion::create(['lesson_id' => $l1->id, 'question' => 'Qual?', 'options' => [['text' => 'a'], ['text' => 'b']], 'correct_index' => 1, 'explanation' => 'Porque b.']);

        $page = Livewire::actingAs($user)->test(CoursePage::class, ['slug' => $course->slug])
            ->assertSet('lessonId', $l1->id)
            ->assertSee('Curso A aula 1')
            ->call('toggleDone', $l1->id)
            ->assertSee('+10 pontos')
            ->call('selectLesson', $l2->id)->assertSet('lessonId', $l2->id)
            ->call('toggleDone', $l2->id);

        $this->assertNull(app(AcademyCertificates::class)->find($user, $course)); // falta entregar a prova

        $page->call('selectLesson', $l1->id)
            ->assertSee('Entregar prova')->assertDontSee('Gabarito da prova') // antes de entregar não há gabarito
            ->set("selected.{$q->id}", 1)->call('deliverQuiz', $l1->id)
            ->assertSee('Gabarito da prova')->assertSee('resposta certa')->assertSee('Porque b.')
            ->assertSee('Certificado emitido')->assertSee('Baixar certificado')
            ->call('downloadCertificate')->assertFileDownloaded();

        $this->assertNotNull(app(AcademyCertificates::class)->find($user, $course));
        $this->assertSame(10 + 10 + 10 + 50, app(AcademyPoints::class)->total($user));
    }

    public function test_course_page_opens_on_the_next_lesson_and_blocks_courses_outside_the_contract(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');
        $course = $this->course('Curso A', 3);
        $this->finish($user, $course, 1);
        $fechado = Course::create(['title' => 'Curso fechado', 'slug' => 'fechado-'.uniqid(), 'is_published' => true]);
        Lesson::create(['course_id' => $fechado->id, 'title' => 'x', 'feature_key' => 'tabela_maintenance_orders', 'position' => 1]);
        $rascunho = $this->course('Curso rascunho', 1, false);

        Livewire::actingAs($user)->test(CoursePage::class, ['slug' => $course->slug])
            ->assertSet('lessonId', $course->lessons[1]->id); // abre na primeira aula ainda não concluída

        $this->actingAs($user)->get('/academia/curso/'.$fechado->slug)->assertNotFound();
        $this->actingAs($user)->get('/academia/curso/'.$rascunho->slug)->assertNotFound();
        $this->actingAs($user)->get('/academia/curso/'.$course->slug)->assertOk()->assertSee('Curso A');
    }

    public function test_the_team_screen_is_admin_only_and_never_shows_another_company(): void
    {
        $a = $this->tenant('Empresa A');
        $b = $this->tenant('Empresa B');
        $course = $this->course('Curso A', 2);
        $gestora = $this->user($a, 'Gestora Ana', admin: true);
        $maria = $this->user($a, 'Maria Colab');
        $this->user($a, 'Pedro Parado');
        $joao = $this->user($b, 'Joao Outra Empresa');
        $this->finish($maria, $course, 2);
        $this->finish($joao, $course, 2);
        app(AcademyCertificates::class)->issueIfEligible($maria, $course);

        $this->actingAs($maria)->get('/academia/equipe')->assertForbidden();

        Livewire::actingAs($gestora)->test(TeamPage::class)
            ->set('period', 'all')
            ->assertSee('Maria Colab')->assertSee('Pedro Parado')->assertDontSee('Joao Outra Empresa')
            ->set('status', 'inativos')->assertSee('Pedro Parado')->assertDontSee('Maria Colab')
            ->set('status', 'certificados')->assertSee('Maria Colab')->assertDontSee('Pedro Parado')
            ->set('status', '')->set('search', 'pedro')->assertSee('Pedro Parado')->assertDontSee('Maria Colab')
            ->set('search', '')->call('sortBy', 'name')->assertSee('Gestora Ana');
    }

    public function test_ranking_only_shows_the_own_company(): void
    {
        $a = $this->user($this->tenant('Empresa A'), 'Aluna Da A');
        $b = $this->user($this->tenant('Empresa B'), 'Aluno Da B');
        $course = $this->course('Curso A', 1);
        $this->finish($a, $course, 1);
        $this->finish($b, $course, 1);

        Livewire::actingAs($a)->test(RankingPage::class)->assertSee('Aluna Da A')->assertDontSee('Aluno Da B');
    }

    public function test_the_background_theme_is_configurable_and_falls_back_safely(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');

        foreach (['aurora', 'planta', 'ondas'] as $theme) {
            config(['oravel.academy.theme' => $theme]);
            $this->actingAs($user)->get('/academia/inicio')->assertOk()->assertSee("ac-theme-{$theme}", false);
        }

        config(['oravel.academy.theme' => '"><script>alert(1)</script>']);
        $this->actingAs($user)->get('/academia/inicio')->assertOk()->assertSee('ac-theme-aurora', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_grade_is_null_until_the_exam_is_delivered_and_blank_answers_count_as_zero(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');
        $course = $this->course('Curso com prova', 1);
        $lesson = $course->lessons()->first();
        $q1 = LessonQuestion::create(['lesson_id' => $lesson->id, 'question' => 'P1', 'options' => [['text' => 'a'], ['text' => 'b']], 'correct_index' => 0]);
        LessonQuestion::create(['lesson_id' => $lesson->id, 'question' => 'P2', 'options' => [['text' => 'a'], ['text' => 'b']], 'correct_index' => 0]);

        $svc = app(AcademyOverview::class);
        $c = $svc->courses($user)->first();
        $this->assertNull($c['nota']);                 // prova não entregue: sem nota (não vira "0,0")
        $this->assertSame(1, $c['quizzes_total']);
        $this->assertSame(0, $c['quizzes_delivered']);

        Livewire::actingAs($user)->test(Home::class)->assertSee('Prova pendente');

        // acerta a P1 e deixa a P2 em branco: 1 de 2 = nota 5,0 (a em branco vale zero)
        app(AcademyPoints::class)->deliver($user, $lesson, [$q1->id => 0]);
        $c = $svc->courses($user)->first();
        $this->assertSame(5.0, $c['nota']);
        $this->assertSame(1, $c['quizzes_delivered']);
        $this->assertSame(2, $c['quiz_graded']);
    }

    public function test_lessons_without_a_video_show_the_in_production_notice(): void
    {
        $user = $this->user($this->tenant('Cliente A'), 'Aluno');
        $course = $this->course('Curso A', 2);
        [$semVideo, $comVideo] = $course->lessons;
        $comVideo->update(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        Livewire::actingAs($user)->test(CoursePage::class, ['slug' => $course->slug])
            ->call('selectLesson', $semVideo->id)
            ->assertSee('Vídeo em produção')->assertDontSeeHtml('<iframe')
            ->call('selectLesson', $comVideo->id)
            ->assertSeeHtml('<iframe')->assertDontSee('Vídeo em produção');
    }
}
