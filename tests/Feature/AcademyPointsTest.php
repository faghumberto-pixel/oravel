<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\CourseResource\Pages\AcademyPointsReport;
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

    private function finish(User $user, Course $course, int $howMany): void
    {
        foreach ($course->lessons()->limit($howMany)->get() as $lesson) {
            LessonProgress::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id], ['tenant_id' => $user->tenant_id, 'completed_at' => now()]);
            app(AcademyPoints::class)->awardRead($user, $lesson);
        }
    }

    private function questions(Lesson $lesson, int $n = 3): array
    {
        return collect(range(1, $n))->map(fn ($i) => LessonQuestion::create([
            'lesson_id' => $lesson->id, 'question' => "Pergunta $i?",
            'options' => [['text' => 'a'], ['text' => 'b'], ['text' => 'c']], 'correct_index' => 1, 'explanation' => 'Porque b.',
        ]))->all();
    }

    public function test_reading_a_lesson_gives_points_once(): void
    {
        $user = $this->tenantUser('Cliente A');
        $lesson = $this->course(3)->lessons()->first();
        $svc = app(AcademyPoints::class);

        $this->assertSame(10, $svc->awardRead($user, $lesson));
        $this->assertSame(0, $svc->awardRead($user, $lesson)); // desmarcar e marcar de novo não paga de novo
        $this->assertSame(10, $this->points($user));
    }

    public function test_delivering_the_quiz_grades_everything_at_once_blank_answers_are_zero_and_it_cannot_be_redone(): void
    {
        $user = $this->tenantUser('Cliente A');
        $lesson = $this->course()->lessons()->first();
        [$q1, $q2, $q3] = $this->questions($lesson);
        $svc = app(AcademyPoints::class);

        // acerta a 1ª (índice 1), erra a 2ª (índice 0) e deixa a 3ª em branco
        $r = $svc->deliver($user, $lesson, [$q1->id => 1, $q2->id => 0]);

        $this->assertFalse($r['already']);
        $this->assertSame(1, $r['submission']->correct_answers);
        $this->assertSame(3, $r['submission']->total_questions);
        $this->assertSame(3.3, $r['submission']->grade());      // 1 de 3: a em branco vale zero
        $this->assertSame(10, $r['gained']);                    // só o acerto pontua
        $this->assertSame(10, $this->points($user));
        $this->assertNull(LessonAnswer::where('question_id', $q3->id)->first()->selected_index);

        // não refaz: nova entrega devolve a mesma e não pontua mais nada
        $again = $svc->deliver($user, $lesson, [$q1->id => 1, $q2->id => 1, $q3->id => 1]);
        $this->assertTrue($again['already']);
        $this->assertSame(0, $again['gained']);
        $this->assertSame(1, $again['submission']->correct_answers);
        $this->assertSame(10, $this->points($user));
    }

    public function test_an_out_of_range_choice_counts_as_blank(): void
    {
        $user = $this->tenantUser('Cliente A');
        $lesson = $this->course()->lessons()->first();
        [$q1] = $this->questions($lesson, 1);

        $r = app(AcademyPoints::class)->deliver($user, $lesson, [$q1->id => 99]);

        $this->assertSame(0, $r['submission']->correct_answers);
        $this->assertNull(LessonAnswer::where('question_id', $q1->id)->first()->selected_index);
    }

    public function test_the_certificate_needs_all_lessons_all_exams_delivered_and_the_minimum_grade(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course(2);
        [$l1, $l2] = $course->lessons;
        [$q1, $q2] = $this->questions($l1, 2);
        $certs = app(AcademyCertificates::class);
        $svc = app(AcademyPoints::class);

        $this->finish($user, $course, 2);
        $this->assertNull($certs->issueIfEligible($user, $course), 'aulas concluídas mas prova não entregue');

        // 1 de 2 certas = nota 5,0, abaixo da mínima (7,0)
        $svc->deliver($user, $l1, [$q1->id => 1, $q2->id => 0]);
        $this->assertNull($certs->issueIfEligible($user, $course), 'nota abaixo da mínima');

        // outra pessoa tira 10 e recebe o certificado; o código é único e a emissão não duplica
        $outra = $this->tenantUser('Cliente B');
        $this->finish($outra, $course, 2);
        $svc->deliver($outra, $l1, [$q1->id => 1, $q2->id => 1]);
        $cert = $certs->issueIfEligible($outra, $course);
        $this->assertNotNull($cert);
        $this->assertMatchesRegularExpression('/^OA-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $cert->code);
        $this->assertSame($cert->id, $certs->issueIfEligible($outra, $course)->id);

        // a nota mínima é configurável
        config(['oravel.academy.passing_grade' => 5.0]);
        $this->assertNotNull($certs->issueIfEligible($user, $course), 'nota 5,0 passa com mínima 5,0');
    }

    public function test_the_certificate_pdf_is_generated_and_the_public_page_confirms_authenticity(): void
    {
        $user = $this->tenantUser('Cliente A');
        $course = $this->course(1);
        $this->finish($user, $course, 1);
        $cert = app(AcademyCertificates::class)->issueIfEligible($user, $course);
        $this->assertNotNull($cert);

        $this->assertStringStartsWith('%PDF', app(AcademyCertificates::class)->pdf($cert));
        $this->get('/certificado/'.strtolower($cert->code))->assertOk()
            ->assertSee('Certificado autêntico')->assertSee('Aluno Cliente A')->assertSee('Primeiros passos');
        $this->get('/certificado/OA-XXXX-0000')->assertOk()->assertSee('Certificado não encontrado');
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
