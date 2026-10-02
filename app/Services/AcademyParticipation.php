<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Participacao dos colaboradores na Academia -- UMA fonte de calculo pras duas telas
 * (admin do cliente: $tenantId fixo = o proprio tenant; Central: todos ou um cliente).
 * Tudo le as tabelas direto, com filtro EXPLICITO de tenant (nao depende do escopo global,
 * pra a Central poder somar clientes). Periodo: conta o que aconteceu desde $since
 * (pontos = linhas criadas no periodo; aulas = concluidas no periodo).
 */
class AcademyParticipation
{
    public const PERIODS = ['7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias', 'all' => 'Todo o período'];

    public function since(string $period): ?Carbon
    {
        return isset(self::PERIODS[$period]) && $period !== 'all' ? now()->subDays((int) $period)->startOfDay() : null;
    }

    /** Colaboradores do(s) cliente(s) com os numeros de cada um (uma linha por usuario). */
    public function usersQuery(?string $tenantId, ?Carbon $since): Builder
    {
        $s = $since?->toDateTimeString();
        [$availableSql, $availableBindings] = $this->availableLessonsCase();
        $when = fn (string $col) => $s ? " and {$col} >= '{$s}'" : '';

        return User::query()->withoutGlobalScopes()
            ->whereNotNull('users.tenant_id')
            ->when($tenantId, fn ($q) => $q->where('users.tenant_id', $tenantId))
            ->leftJoin('tenants', 'tenants.id', '=', 'users.tenant_id')
            ->select('users.*')
            ->selectRaw('tenants.name as tenant_name')
            ->selectRaw($availableSql.' as lessons_available', $availableBindings)
            ->selectRaw('(select count(*) from lesson_progress la where la.user_id = users.id) as lessons_done_all')
            ->selectRaw('(select coalesce(sum(points),0) from academy_points p where p.user_id = users.id'.$when('p.created_at').') as points')
            ->selectRaw('(select count(*) from lesson_progress lp where lp.user_id = users.id'.$when('lp.completed_at').') as lessons_done')
            ->selectRaw('(select count(*) from academy_certificates c where c.user_id = users.id'.$when('c.issued_at').') as certificates')
            ->selectRaw("(select coalesce(sum(seconds),0)/60 from academy_points t where t.user_id = users.id and t.source = 'time'".$when('t.created_at').') as minutes')
            ->selectRaw('(select count(*) from lesson_answers a where a.user_id = users.id'.$when('a.updated_at').') as quiz_total')
            ->selectRaw('(select count(*) from lesson_answers a where a.user_id = users.id and a.is_correct'.$when('a.updated_at').') as quiz_correct')
            ->selectRaw('(select max(ts) from (select updated_at as ts from academy_points where user_id = users.id union all select completed_at from lesson_progress where user_id = users.id) x) as last_activity');
    }

    /**
     * "Quantas aulas esse cliente tem liberadas" como um CASE por tenant (o contrato decide quais
     * aulas contam; isso nao da' pra fazer em SQL puro, entao calculamos por tenant e injetamos).
     *
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function availableLessonsCase(): array
    {
        $lessons = Lesson::query()->whereHas('course', fn ($q) => $q->published())->get(['id', 'feature_key']);
        $tenants = Tenant::withoutGlobalScopes()->with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->get();

        $sql = 'case users.tenant_id';
        $bindings = [];
        foreach ($tenants as $tenant) {
            if (! $tenant->plan?->hasFeature('tabela_courses')) {
                continue;
            }
            $count = $lessons->filter(fn ($l) => ! $l->feature_key || $tenant->plan->hasFeature($l->feature_key))->count();
            $sql .= ' when ? then '.$count;
            $bindings[] = $tenant->id;
        }

        return [$sql.' else 0 end', $bindings];
    }

    /**
     * Cartoes de resumo.
     *
     * @return array{people:int, active:int, lessons:int, points:int, certificates:int, minutes:int, quiz_rate:?int}
     */
    public function summary(?string $tenantId, ?Carbon $since): array
    {
        $rows = DB::query()->fromSub($this->usersQuery($tenantId, $since)->getQuery(), 'u')->get();

        $quizTotal = (int) $rows->sum('quiz_total');
        $available = (int) $rows->sum('lessons_available');

        return [
            'people' => $rows->count(),
            'active' => $rows->filter(fn ($r) => $r->points > 0 || $r->lessons_done > 0)->count(),
            'lessons' => (int) $rows->sum('lessons_done'),
            'points' => (int) $rows->sum('points'),
            'certificates' => (int) $rows->sum('certificates'),
            'minutes' => (int) round($rows->sum('minutes')),
            'quiz_rate' => $quizTotal ? (int) round($rows->sum('quiz_correct') / $quizTotal * 100) : null,
            'certified_percent' => $rows->count() ? (int) round($rows->filter(fn ($r) => $r->certificates > 0)->count() / $rows->count() * 100) : 0,
            'active_percent' => $rows->count() ? (int) round($rows->filter(fn ($r) => $r->points > 0 || $r->lessons_done > 0)->count() / $rows->count() * 100) : 0,
            // quanto do conteudo liberado pro contrato a equipe ja concluiu (todo o periodo)
            'completion' => $available ? (int) min(100, round($rows->sum('lessons_done_all') / $available * 100)) : 0,
        ];
    }

    /**
     * Por curso publicado: quantas pessoas ja concluiram aulas e quantas receberam certificado.
     *
     * @return Collection<int, array{title:string, lessons:int, started:int, certified:int, percent:int}>
     */
    public function courses(?string $tenantId, ?Carbon $since): Collection
    {
        $people = max(1, (int) $this->usersQuery($tenantId, null)->toBase()->getCountForPagination());

        return Course::published()->orderBy('position')->get()->map(function (Course $course) use ($tenantId, $since, $people) {
            $lessonIds = Lesson::where('course_id', $course->id)->pluck('id');

            $started = DB::table('lesson_progress')->whereIn('lesson_id', $lessonIds)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->when($since, fn ($q) => $q->where('completed_at', '>=', $since))
                ->distinct()->count('user_id');

            $certified = DB::table('academy_certificates')->where('course_id', $course->id)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->when($since, fn ($q) => $q->where('issued_at', '>=', $since))
                ->count();

            return [
                'title' => $course->title,
                'lessons' => $lessonIds->count(),
                'started' => $started,
                'certified' => $certified,
                'percent' => (int) min(100, round($started / $people * 100)),
                'certified_percent' => (int) min(100, round($certified / $people * 100)),
            ];
        })->filter(fn ($c) => $c['started'] > 0 || $c['certified'] > 0)->values();
    }

    /**
     * Aulas concluidas por semana nas ultimas $weeks semanas (grafico de atividade).
     *
     * @return array<string, int> "dd/mm" (segunda-feira da semana) => aulas
     */
    public function weekly(?string $tenantId, int $weeks = 8): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $rows = DB::table('lesson_progress')
            ->where('completed_at', '>=', $start)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->pluck('completed_at');

        $series = [];
        for ($i = 0; $i < $weeks; $i++) {
            $from = $start->copy()->addWeeks($i);
            $to = $from->copy()->addWeek();
            $series[$from->format('d/m')] = $rows->filter(fn ($d) => Carbon::parse($d)->gte($from) && Carbon::parse($d)->lt($to))->count();
        }

        return $series;
    }
}
