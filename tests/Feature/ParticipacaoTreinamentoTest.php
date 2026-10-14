<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\ParticipacaoTreinamento;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Models\LessonQuizSubmission;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TreinamentoResultado;
use App\Models\User;
use App\Services\AcademyClientReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quadro da Central: participacao por cliente na Academia (acessos mes a mes e
 * dia a dia, cursos, exercicios e provas com acertos) e testes publicos por empresa.
 */
class ParticipacaoTreinamentoTest extends TestCase
{
    use DatabaseTransactions;

    private Course $curso;

    /** @var array<int, Lesson> */
    private array $aulas = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['oravel.super_admins' => ['equipe@oravel.test']]);

        $this->curso = Course::create(['title' => 'Curso '.Str::random(5), 'slug' => 'curso-'.Str::random(8), 'position' => 1, 'is_published' => true]);
        foreach ([1, 2] as $i) {
            $this->aulas[$i] = Lesson::create(['course_id' => $this->curso->id, 'title' => "Aula $i", 'position' => $i, 'page_url' => 'https://t.test/'.Str::random(8).'.html']);
        }
    }

    private function cliente(string $nome, array $features = ['tabela_courses']): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano '.Str::random(6), 'price' => 1, 'base_price' => 1, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);

        return Tenant::create(['name' => $nome, 'slug' => Str::slug($nome).'-'.Str::random(4), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function pessoa(Tenant $t, string $nome, ?string $email = null): User
    {
        return User::create([
            'name' => $nome, 'email' => $email ?? Str::slug($nome).'-'.Str::random(4).'@cliente.test',
            'password' => bcrypt('x'), 'tenant_id' => $t->id,
        ]);
    }

    private function acesso(User $u, \DateTimeInterface $quando, string $path = '/academia/inicio'): void
    {
        DB::table('user_activity_logs')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $u->tenant_id, 'user_id' => $u->id, 'method' => 'GET',
            'path' => $path, 'action' => 'view', 'created_at' => $quando, 'updated_at' => $quando,
        ]);
    }

    private function prova(User $u, Lesson $aula, int $acertos, int $total, ?\DateTimeInterface $quando = null): void
    {
        LessonQuizSubmission::withoutGlobalScopes()->create([
            'tenant_id' => $u->tenant_id, 'user_id' => $u->id, 'lesson_id' => $aula->id,
            'total_questions' => $total, 'correct_answers' => $acertos, 'delivered_at' => $quando ?? now(),
        ]);
    }

    public function test_clients_table_counts_access_exercises_exams_and_courses(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $b = $this->cliente('Locadora Beta (sem uso)');
        $ana = $this->pessoa($a, 'Ana');
        $bia = $this->pessoa($a, 'Bia');
        $this->pessoa($b, 'Caio');

        // Ana: 3 acessos em 2 dias, leu as 2 aulas (curso concluido), 4 exercicios (3 certos), 1 prova 8/10
        $this->acesso($ana, now()->subDays(2));
        $this->acesso($ana, now()->subDays(2)->addHour());
        $this->acesso($ana, now()->subDay());
        foreach ($this->aulas as $aula) {
            LessonProgress::withoutGlobalScopes()->create(['tenant_id' => $a->id, 'user_id' => $ana->id, 'lesson_id' => $aula->id, 'completed_at' => now()->subDay()]);
        }
        foreach ([true, true, true, false] as $k => $certa) {
            $q = LessonQuestion::create(['lesson_id' => $this->aulas[1]->id, 'question' => "Q$k", 'options' => [['text' => 'a'], ['text' => 'b']], 'correct_index' => 0, 'position' => $k + 2]);
            LessonAnswer::withoutGlobalScopes()->create(['tenant_id' => $a->id, 'user_id' => $ana->id, 'question_id' => $q->id, 'selected_index' => 0, 'is_correct' => $certa, 'attempts' => 1]);
        }
        $this->prova($ana, $this->aulas[1], 8, 10);

        // Bia: so 1 acesso e uma prova 5/10
        $this->acesso($bia, now()->subDay());
        $this->prova($bia, $this->aulas[2], 5, 10);

        $linhas = app(AcademyClientReport::class)->clients(now()->subDays(30))->keyBy('cliente');

        $alfa = $linhas['Locadora Alfa'];
        $this->assertSame(2, $alfa['colaboradores']);
        $this->assertSame(2, $alfa['participantes']);
        $this->assertSame(4, $alfa['acessos']);
        $this->assertSame(3, $alfa['dias_com_acesso']);   // Ana: 2 dias; Bia: 1 dia
        $this->assertSame(2, $alfa['aulas']);
        $this->assertSame(1, $alfa['cursos']);
        $this->assertSame(4, $alfa['exercicios']);
        $this->assertSame(3, $alfa['exercicios_acertos']);
        $this->assertSame(2, $alfa['provas']);
        $this->assertSame(13, $alfa['provas_acertos']);
        $this->assertSame(20, $alfa['provas_total']);
        $this->assertEquals(6.5, $alfa['nota']);

        $beta = $linhas['Locadora Beta (sem uso)'];
        $this->assertSame(0, $beta['participantes']);
        $this->assertSame(0, $beta['acessos']);
        $this->assertNull($beta['nota']);
    }

    public function test_the_oravel_staff_is_left_out(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $equipe = $this->pessoa($a, 'Equipe', 'equipe@oravel.test');
        $this->acesso($equipe, now());
        $this->prova($equipe, $this->aulas[1], 10, 10);

        $alfa = app(AcademyClientReport::class)->clients(now()->subDays(30))->firstWhere('cliente', 'Locadora Alfa');

        $this->assertSame(0, $alfa['colaboradores']);
        $this->assertSame(0, $alfa['acessos']);
        $this->assertSame(0, $alfa['provas']);
    }

    public function test_the_period_filters_old_activity_out(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $ana = $this->pessoa($a, 'Ana');
        $this->acesso($ana, now()->subDays(60));
        $this->prova($ana, $this->aulas[1], 9, 10, now()->subDays(60));

        $recente = app(AcademyClientReport::class)->clients(now()->subDays(30))->firstWhere('cliente', 'Locadora Alfa');
        $todo = app(AcademyClientReport::class)->clients(null)->firstWhere('cliente', 'Locadora Alfa');

        $this->assertSame(0, $recente['acessos']);
        $this->assertSame(0, $recente['provas']);
        $this->assertSame(1, $todo['acessos']);
        $this->assertSame(1, $todo['provas']);
    }

    public function test_a_course_only_counts_when_every_released_lesson_was_read(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $ana = $this->pessoa($a, 'Ana');
        LessonProgress::withoutGlobalScopes()->create(['tenant_id' => $a->id, 'user_id' => $ana->id, 'lesson_id' => $this->aulas[1]->id, 'completed_at' => now()]);

        $alfa = app(AcademyClientReport::class)->clients(null)->firstWhere('cliente', 'Locadora Alfa');

        $this->assertSame(0, $alfa['cursos']);
        $this->assertSame(1, $alfa['aulas']);
    }

    public function test_monthly_and_daily_series_count_only_academy_views(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $ana = $this->pessoa($a, 'Ana');
        $bia = $this->pessoa($a, 'Bia');
        $this->acesso($ana, now());
        $this->acesso($bia, now());
        $this->acesso($ana, now()->subDays(3));
        $this->acesso($ana, now()->subMonths(2)->startOfMonth()->addDay());
        $this->acesso($ana, now(), '/admin/ativos'); // fora da Academia: nao conta

        $mensal = app(AcademyClientReport::class)->monthly(12);
        $linha = $mensal['rows']->firstWhere('cliente', 'Locadora Alfa');
        $atual = now()->format('Y-m');

        $this->assertCount(12, $mensal['months']);
        $this->assertSame(['acessos' => 3, 'pessoas' => 2], $linha['meses'][$atual]);
        $this->assertSame(1, $linha['meses'][now()->subMonths(2)->format('Y-m')]['acessos']);

        $diario = app(AcademyClientReport::class)->daily(30, $a->id);
        $this->assertCount(30, $diario);
        $hoje = $diario->firstWhere('dia', now()->format('Y-m-d'));
        $this->assertSame(2, $hoje['acessos']);
        $this->assertSame(2, $hoje['pessoas']);
        $this->assertSame(1, $diario->firstWhere('dia', now()->subDays(3)->format('Y-m-d'))['acessos']);
    }

    public function test_public_tests_are_grouped_by_company(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $nova = fn (array $x) => TreinamentoResultado::create(array_merge([
            'treinamento' => 'cadastros-propostas-receber', 'acertos' => 10, 'total' => 15, 'nota' => 6.7,
            'aprovado' => false, 'respostas' => [],
        ], $x));
        $nova(['nome' => 'Maria', 'funcao' => 'Vendas', 'empresa' => 'locadora alfa', 'tenant_id' => $a->id]);
        $nova(['nome' => 'Maria', 'funcao' => 'Vendas', 'empresa' => 'Locadora Alfa', 'tenant_id' => $a->id, 'acertos' => 14, 'nota' => 9.3, 'aprovado' => true]);
        $nova(['nome' => 'João', 'funcao' => 'Oficina', 'empresa' => 'Outra Empresa']);

        $linhas = app(AcademyClientReport::class)->publicTests(null)->keyBy('empresa');

        $alfa = $linhas['Locadora Alfa'];
        $this->assertTrue($alfa['cadastrado']);
        $this->assertSame(1, $alfa['pessoas']);
        $this->assertSame(2, $alfa['tentativas']);
        $this->assertSame(1, $alfa['aprovados']);
        $this->assertSame(24, $alfa['acertos']);
        $this->assertEquals(9.3, $alfa['melhor_nota']);

        $this->assertFalse($linhas['Outra Empresa']['cadastrado']);
    }

    public function test_the_page_renders_for_the_super_admin_with_the_numbers(): void
    {
        $a = $this->cliente('Locadora Alfa');
        $ana = $this->pessoa($a, 'Ana');
        $this->cliente('Locadora Beta');
        $this->acesso($ana, now());
        $this->prova($ana, $this->aulas[1], 7, 10);

        $super = $this->pessoa($a, 'Super', 'equipe@oravel.test');
        $this->actingAs($super);

        Livewire::test(ParticipacaoTreinamento::class)
            ->assertSee('Locadora Alfa')
            ->assertSee('sem participação') // a Beta
            ->assertSee('7 de 10')
            ->call('select', $a->id)
            ->assertSee('Colaboradores de Locadora Alfa')
            ->assertSee('Ana');
    }

    public function test_only_the_super_admin_can_open_the_page(): void
    {
        $a = $this->cliente('Locadora Alfa');

        $this->actingAs($this->pessoa($a, 'Comum'));
        $this->assertFalse(ParticipacaoTreinamento::canAccess());

        $this->actingAs($this->pessoa($a, 'Super', 'equipe@oravel.test'));
        $this->assertTrue(ParticipacaoTreinamento::canAccess());
    }
}
