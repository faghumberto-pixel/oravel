<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\CourseResource\Pages\AcademyPointsReport;
use App\Filament\Resources\CourseResource\Pages\Ranking;
use App\Filament\Resources\CourseResource\Pages\ViewCourse;
use App\Models\AcademyCertificate;
use App\Models\AcademyPoint;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AcademyCertificates;
use App\Services\AcademyPoints;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Academia: pontos (leitura, quiz, tempo, bônus de curso), ranking por empresa e certificado. */
class AcademyPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tenantUser(string $name, array $features = ['tabela_courses']): User
    {
        $plan = Plan::create(['name' => 'Plano '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Aluno '.$name, 'email' => 'aluno-'.uniqid().'@teste.com', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_curso', 'guard_name' => 'web']));

        return $user->fresh();
    }

    private function course(int $lessons = 2): Course
    {
        $course = Course::create(['title' => 'Primeiros passos', 'slug' => 'pp-'.uniqid(), 'is_published' => true]);
        foreach (range(1, $lessons) as $i) {
            Lesson::create(['course_id' => $course->id, 'title' => "Aula $i", 'position' => $i]);
        }

        return $course;
    }

    private function question(Lesson $lesson, int $correct = 1): LessonQuestion
    {
        return LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'question' => 'Qual é a certa?',
            'options' => [['text' => 'A'], ['text' => 'B'], ['text' => 'C']],
            'correct_index' => $correct,
            'explanation' => 'Porque sim.',
        ]);
    }

    private function asApp(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function points(User $user): int
    {
        return app(AcademyPoints::class)->total($user);
    }

    public function test_reading_a_lesson_gives_points_once_even_if_unchecked_and_checked_again(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course(3);
        $lesson = $course->lessons()->first();
        $this->asApp($user);

        $page = Livewire::test(ViewCourse::class, ['record' => $course->getKey()]);
        $page->call('toggleDone', $lesson->id);
        $this->assertSame(10, $this->points($user));

        $page->call('toggleDone', $lesson->id)->call('toggleDone', $lesson->id); // desmarca e marca de novo
        $this->assertSame(10, $this->points($user));
    }

    public function test_quiz_pays_only_the_first_correct_answer_and_wrong_ones_cost_nothing(): void
    {
        $user = $this->tenantUser('Cliente A');
        $q = $this->question($this->course()->lessons()->first(), correct: 1);
        $svc = app(AcademyPoints::class);

        [$ok, $gained] = $svc->answer($user, $q, 0);
        $this->assertFalse($ok);
        $this->assertSame(0, $gained);

        [$ok, $gained] = $svc->answer($user, $q, 1);
        $this->assertTrue($ok);
        $this->assertSame(10, $gained);

        [, $again] = $svc->answer($user, $q, 1);   // acertar de novo não paga
        $svc->answer($user, $q, 2);                // errar depois não tira nem "desacerta"
        $this->assertSame(0, $again);
        $this->assertSame(10, $this->points($user));
        $this->assertTrue(LessonAnswer::where('user_id', $user->id)->first()->is_correct);
    }

    public function test_active_time_gives_a_point_per_minute_with_a_cap_and_ignores_spam_and_pauses(): void
    {
        $user = $this->tenantUser('Cliente A');
        $lesson = $this->course()->lessons()->first();
        $svc = app(AcademyPoints::class);
        $t = Carbon::parse('2026-10-02 10:00:00');

        Carbon::setTestNow($t);
        $svc->heartbeat($user, $lesson);                       // abriu: 0 s
        Carbon::setTestNow($t->copy()->addSeconds(5));
        $svc->heartbeat($user, $lesson);                       // batida colada: ignorada
        foreach ([30, 60, 90] as $s) {                         // 3 batidas de 30 s = 90 s
            Carbon::setTestNow($t->copy()->addSeconds($s));
            $svc->heartbeat($user, $lesson);
        }
        $this->assertSame(1, $this->points($user));            // 90 s = 1 minuto cheio

        Carbon::setTestNow($t->copy()->addMinutes(30));        // pausa longa: não credita o intervalo
        $svc->heartbeat($user, $lesson);
        $this->assertSame(90, (int) AcademyPoint::where('source', 'time')->value('seconds'));

        $now = $t->copy()->addMinutes(30);                     // estudo contínuo por 2 horas: respeita o teto (10)
        for ($i = 0; $i < 240; $i++) {
            $now = $now->copy()->addSeconds(30);
            Carbon::setTestNow($now);
            $svc->heartbeat($user, $lesson);
        }
        $this->assertSame(10, $this->points($user));
    }

    public function test_finishing_every_available_lesson_gives_the_course_bonus_once(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course(2);
        $svc = app(AcademyPoints::class);

        foreach ($course->lessons as $lesson) {
            LessonProgress::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
            $svc->awardRead($user, $lesson);
        }
        $this->assertSame(10 + 10 + 50, $this->points($user));

        $svc->awardRead($user, $course->lessons->first()); // repetir não paga nada
        $this->assertSame(70, $this->points($user));
    }

    public function test_the_course_bonus_only_counts_lessons_released_in_the_contract(): void
    {
        $user = $this->tenantUser('Cliente A', ['tabela_courses']);
        $course = $this->course(1);
        $liberada = $course->lessons()->first();
        Lesson::create(['course_id' => $course->id, 'title' => 'Fechada', 'feature_key' => 'tabela_maintenance_orders', 'position' => 5]);
        $this->asApp($user);

        LessonProgress::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'lesson_id' => $liberada->id, 'completed_at' => now()]);

        $this->assertSame(60, app(AcademyPoints::class)->awardRead($user, $liberada)); // 10 + bônus, mesmo com 1 aula fechada
    }

    public function test_the_ranking_only_shows_people_from_the_own_company(): void
    {
        $a1 = $this->tenantUser('Empresa A');
        $b1 = $this->tenantUser('Empresa B');
        $lesson = $this->course()->lessons()->first();
        $svc = app(AcademyPoints::class);
        $svc->awardRead($a1, $lesson);
        $svc->awardRead($b1, $lesson);

        $ranking = $svc->ranking($a1);
        $this->assertCount(1, $ranking);
        $this->assertSame($a1->id, $ranking->first()->user_id);

        $this->asApp($a1);
        Livewire::test(Ranking::class)->assertSee('Aluno Empresa A')->assertDontSee('Aluno Empresa B');
    }

    public function test_answering_through_the_course_page_updates_points_and_shows_feedback(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course();
        $lesson = $course->lessons()->first();
        $q = $this->question($lesson, correct: 2);
        $this->asApp($user);

        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])
            ->call('open', $lesson->id)
            ->assertSee('Qual é a certa?')
            ->set("selected.{$q->id}", 0)->call('answerQuestion', $q->id)
            ->assertSee('Não foi dessa vez')
            ->set("selected.{$q->id}", 2)->call('answerQuestion', $q->id)
            ->assertSee('Correto!')->assertSee('+10 pontos')->assertSee('Porque sim.');

        $this->assertSame(10, $this->points($user));
    }

    public function test_certificate_requires_all_lessons_and_all_quiz_answers_and_is_issued_once(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course(2);
        $q = $this->question($course->lessons()->first(), correct: 1);
        $this->asApp($user);
        $certs = app(AcademyCertificates::class);

        $page = Livewire::test(ViewCourse::class, ['record' => $course->getKey()]);
        foreach ($course->lessons as $lesson) {
            $page->call('toggleDone', $lesson->id);
        }
        $this->assertNull($certs->find($user, $course), 'aulas concluídas mas o quiz não foi acertado');

        $page->set("selected.{$q->id}", 0)->call('answerQuestion', $q->id);
        $this->assertNull($certs->find($user, $course), 'resposta errada não libera');

        $page->set("selected.{$q->id}", 1)->call('answerQuestion', $q->id);
        $cert = $certs->find($user, $course);
        $this->assertNotNull($cert);
        $this->assertMatchesRegularExpression('/^OA-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $cert->code);
        $this->assertSame('Primeiros passos', $cert->course_title);
        $page->assertSee($cert->code);

        $certs->issueIfEligible($user, $course); // não duplica
        $this->assertSame(1, AcademyCertificate::withoutGlobalScopes()->count());

        // desmarcar uma aula depois não retira o certificado
        $page->call('toggleDone', $course->lessons->first()->id);
        $this->assertNotNull($certs->find($user, $course));
    }

    public function test_certificate_pdf_downloads_and_the_public_page_confirms_authenticity(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course(1);
        $this->asApp($user);
        $page = Livewire::test(ViewCourse::class, ['record' => $course->getKey()])->call('toggleDone', $course->lessons->first()->id);
        $cert = AcademyCertificate::withoutGlobalScopes()->first();
        $this->assertNotNull($cert);

        $page->call('downloadCertificate')->assertFileDownloaded();
        $this->assertStringStartsWith('%PDF', app(AcademyCertificates::class)->pdf($cert));

        auth()->logout(); // consulta pública, sem login
        $this->get('/certificado/'.strtolower($cert->code))->assertOk()
            ->assertSee('Certificado autêntico')->assertSee('Aluno Cliente A')->assertSee('Primeiros passos');
        $this->get('/certificado/OA-XXXX-0000')->assertOk()->assertSee('Certificado não encontrado');
    }

    public function test_another_user_never_downloads_someone_elses_certificate(): void
    {
        $a = $this->tenantUser('Empresa A');
        $b = $this->tenantUser('Empresa B');
        $course = $this->course(1);
        $this->asApp($a);
        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])->call('toggleDone', $course->lessons->first()->id);

        $this->asApp($b);
        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])
            ->assertDontSee('Baixar certificado')
            ->call('downloadCertificate')
            ->assertStatus(404);
    }

    public function test_central_report_lists_points_by_company(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $a = $this->tenantUser('Empresa A');
        $b = $this->tenantUser('Empresa B');
        $lesson = $this->course()->lessons()->first();
        app(AcademyPoints::class)->awardRead($a, $lesson);
        app(AcademyPoints::class)->awardRead($b, $lesson);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(AcademyPointsReport::class)
            ->assertSee('Empresa A')->assertSee('Empresa B')
            ->assertSee('Aluno Empresa A')->assertSee('Aluno Empresa B');
    }
}
