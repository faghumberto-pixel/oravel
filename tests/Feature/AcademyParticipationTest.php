<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\CourseResource\Pages\AcademyPointsReport;
use App\Livewire\Academy\TeamPage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AcademyParticipation;
use App\Services\AcademyPoints;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Visão dinâmica da participação: admin do cliente (só a própria equipe) e Central (todos os clientes). */
class AcademyParticipationTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name, array $features = ['tabela_courses']): Tenant
    {
        $plan = Plan::create(['name' => 'Plano '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function user(Tenant $tenant, string $name, bool $admin = false): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().'-'.uniqid().'@teste.com', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_curso', 'guard_name' => 'web']));
        if ($admin) {
            $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        }

        return $user->fresh();
    }

    private function course(int $lessons = 4): Course
    {
        $course = Course::create(['title' => 'Primeiros passos', 'slug' => 'pp-'.uniqid(), 'is_published' => true]);
        foreach (range(1, $lessons) as $i) {
            Lesson::create(['course_id' => $course->id, 'title' => "Aula $i", 'position' => $i]);
        }

        return $course;
    }

    private function study(User $user, Course $course, int $howMany): void
    {
        foreach ($course->lessons()->limit($howMany)->get() as $lesson) {
            LessonProgress::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
            app(AcademyPoints::class)->awardRead($user, $lesson);
        }
    }

    private function asApp(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_company_admin_sees_the_team_but_never_another_company(): void
    {
        $a = $this->tenant('Empresa A');
        $b = $this->tenant('Empresa B');
        $course = $this->course();
        $admin = $this->user($a, 'Gestora Ana', admin: true);
        $maria = $this->user($a, 'Maria Colaboradora');
        $joao = $this->user($b, 'Joao De Outra Empresa');
        $this->study($maria, $course, 2);
        $this->study($joao, $course, 4);

        $this->asApp($admin);

        Livewire::test(TeamPage::class)
            ->assertSee('Maria Colaboradora')
            ->assertSee('Gestora Ana')
            ->assertDontSee('Joao De Outra Empresa')
            ->assertSee('2 de 4 aulas');
    }

    public function test_a_regular_collaborator_cannot_open_the_team_view(): void
    {
        $a = $this->tenant('Empresa A');
        $comum = $this->user($a, 'Colaborador Comum');

        $this->asApp($comum);

        Livewire::test(TeamPage::class)->assertForbidden();
    }

    public function test_summary_numbers_and_completion_percentage_follow_the_contract(): void
    {
        $a = $this->tenant('Empresa A');
        $course = $this->course(4);
        Lesson::create(['course_id' => $course->id, 'title' => 'Fechada', 'feature_key' => 'tabela_maintenance_orders', 'position' => 9]); // fora do contrato
        $maria = $this->user($a, 'Maria');
        $this->user($a, 'Pedro Parado');
        $this->study($maria, $course, 2);

        $summary = app(AcademyParticipation::class)->summary($a->id, null);

        $this->assertSame(2, $summary['people']);
        $this->assertSame(1, $summary['active']);
        $this->assertSame(50, $summary['active_percent']);
        $this->assertSame(2, $summary['lessons']);
        // 2 aulas feitas de 4 liberadas pro contrato (a "Fechada" não conta) por 2 pessoas = 2 / (4*2) = 25%
        $this->assertSame(25, $summary['completion']);
        $this->assertSame(0, $summary['certified_percent']);
    }

    public function test_the_period_filter_only_counts_recent_activity(): void
    {
        $a = $this->tenant('Empresa A');
        $course = $this->course(2);
        $maria = $this->user($a, 'Maria');
        $this->study($maria, $course, 1);
        // uma conclusão antiga (60 dias atrás)
        $old = $course->lessons()->reorder('position', 'desc')->first();
        LessonProgress::create(['tenant_id' => $a->id, 'user_id' => $maria->id, 'lesson_id' => $old->id, 'completed_at' => now()->subDays(60)]);

        $svc = app(AcademyParticipation::class);
        $this->assertSame(2, $svc->summary($a->id, $svc->since('all'))['lessons']);
        $this->assertSame(1, $svc->summary($a->id, $svc->since('30'))['lessons']);
        $this->assertNull($svc->since('all'));
    }

    public function test_the_admin_screen_reacts_to_the_period_and_shows_colored_percentage_bars(): void
    {
        $a = $this->tenant('Empresa A');
        $course = $this->course(4);
        $admin = $this->user($a, 'Gestora Ana', admin: true);
        $verde = $this->user($a, 'Aluna Verde');
        $vermelha = $this->user($a, 'Aluna Vermelha');
        $this->study($verde, $course, 4);   // 100%
        $this->study($vermelha, $course, 1); // 25%

        $this->asApp($admin);

        $html = Livewire::test(TeamPage::class)->set('period', 'all')->html();

        $this->assertStringContainsString('#16a34a', $html);  // verde (>= 70%)
        $this->assertStringContainsString('#dc2626', $html);  // vermelho (< 40%)
        $this->assertStringContainsString('data-percent="100"', $html);
        $this->assertStringContainsString('data-percent="25"', $html);
        $this->assertStringContainsString('data-testid="weekly-chart"', $html);
    }

    public function test_status_filter_finds_collaborators_with_no_activity(): void
    {
        $a = $this->tenant('Empresa A');
        $course = $this->course(2);
        $admin = $this->user($a, 'Gestora Ana', admin: true);
        $ativa = $this->user($a, 'Aluna Ativa');
        $this->user($a, 'Pedro Parado');
        $this->study($ativa, $course, 1);

        $this->asApp($admin);

        Livewire::test(TeamPage::class)
            ->set('period', 'all')
            ->set('status', 'inativos')
            ->assertSee('Pedro Parado')
            ->assertDontSee('Aluna Ativa');
    }

    public function test_central_sees_every_company_and_can_filter_by_one(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $a = $this->tenant('Empresa A');
        $b = $this->tenant('Empresa B');
        $course = $this->course(2);
        $this->study($this->user($a, 'Aluna Da A'), $course, 1);
        $this->study($this->user($b, 'Aluno Da B'), $course, 2);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(AcademyPointsReport::class)
            ->set('period', 'all')
            ->assertSee('Aluna Da A')->assertSee('Aluno Da B')
            ->set('tenantId', $a->id)
            ->assertSee('Aluna Da A')->assertDontSee('Aluno Da B');
    }
}
