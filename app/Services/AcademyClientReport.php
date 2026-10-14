<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Tenant;
use App\Models\TreinamentoResultado;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quadro da Central: participação de cada CLIENTE na Academia e nos testes de
 * treinamento. Tudo lê as tabelas direto, com filtro explícito de cliente (não
 * depende do escopo global, para a Central poder somar clientes), e ignora a
 * equipe Oravel (SUPER_ADMINS).
 *
 *  - acessos: telas da Academia abertas (user_activity_logs, path /academia%);
 *  - exercícios: perguntas respondidas (lesson_answers) e quantas estavam certas;
 *  - provas: provas entregues (lesson_quiz_submissions) com acertos/total;
 *  - curso concluído: o colaborador leu todas as aulas do curso que o contrato do
 *    cliente libera.
 */
class AcademyClientReport
{
    /** @return list<string> e-mails da equipe Oravel, em minúsculas */
    private function staff(): array
    {
        return array_values(array_filter(array_map('strtolower', (array) config('oravel.super_admins'))));
    }

    /** Colaboradores de clientes (sem a equipe Oravel): id => tenant_id. */
    private function members(?string $tenantId): Collection
    {
        return User::withoutGlobalScopes()
            ->whereNotNull('tenant_id')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($this->staff(), fn ($q) => $q->whereNotIn(DB::raw('lower(email)'), $this->staff()))
            ->pluck('tenant_id', 'id');
    }

    /**
     * Uma linha por cliente, com os números do período.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function clients(?Carbon $since, ?string $tenantId = null): Collection
    {
        $members = $this->members($tenantId);
        $memberIds = $members->keys()->all();
        $at = fn (string $col) => fn ($q) => $since ? $q->where($col, '>=', $since) : $q;

        $logs = DB::table('user_activity_logs')->whereIn('user_id', $memberIds)->where('path', 'like', '/academia%')->where('action', 'view')
            ->tap($at('created_at'))
            ->selectRaw('user_id, count(*) as n, count(distinct date(created_at)) as dias, max(created_at) as ultimo')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $aulas = DB::table('lesson_progress')->whereIn('user_id', $memberIds)->whereNotNull('completed_at')
            ->tap($at('completed_at'))
            ->selectRaw('user_id, count(*) as n, max(completed_at) as ultimo')->groupBy('user_id')->get()->keyBy('user_id');

        $exercicios = DB::table('lesson_answers')->whereIn('user_id', $memberIds)
            ->tap($at('updated_at'))
            ->selectRaw('user_id, count(*) as n, sum(case when is_correct then 1 else 0 end) as ok, max(updated_at) as ultimo')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $provas = DB::table('lesson_quiz_submissions')->whereIn('user_id', $memberIds)
            ->tap($at('delivered_at'))
            ->selectRaw('user_id, count(*) as n, sum(correct_answers) as ok, sum(total_questions) as total, max(delivered_at) as ultimo')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $tempo = DB::table('academy_points')->whereIn('user_id', $memberIds)->where('source', 'time')
            ->tap($at('last_beat_at'))
            ->selectRaw('user_id, sum(seconds) as s')->groupBy('user_id')->get()->keyBy('user_id');

        $cursos = $this->coursesDone($members, $since);

        $tenants = Tenant::withoutGlobalScopes()->when($tenantId, fn ($q) => $q->whereKey($tenantId))->orderBy('name')->get(['id', 'name']);

        return $tenants->map(function (Tenant $tenant) use ($members, $logs, $aulas, $exercicios, $provas, $tempo, $cursos) {
            $users = $members->filter(fn ($t) => $t === $tenant->id)->keys();
            $sum = fn (Collection $c, string $col) => (int) $users->sum(fn ($id) => (int) ($c[$id]->{$col} ?? 0));
            $participantes = $users->filter(fn ($id) => isset($logs[$id]) || isset($aulas[$id]) || isset($exercicios[$id]) || isset($provas[$id]));
            $ultimo = $users->flatMap(fn ($id) => array_filter([$logs[$id]->ultimo ?? null, $aulas[$id]->ultimo ?? null, $exercicios[$id]->ultimo ?? null, $provas[$id]->ultimo ?? null]))->max();

            $provasTotal = $sum($provas, 'total');
            $provasOk = $sum($provas, 'ok');
            $exerciciosTotal = $sum($exercicios, 'n');

            return [
                'tenant_id' => $tenant->id,
                'cliente' => $tenant->name,
                'colaboradores' => $users->count(),
                'participantes' => $participantes->count(),
                'acessos' => $sum($logs, 'n'),
                'dias_com_acesso' => $sum($logs, 'dias'),
                'aulas' => $sum($aulas, 'n'),
                'cursos' => (int) ($cursos[$tenant->id] ?? 0),
                'exercicios' => $exerciciosTotal,
                'exercicios_acertos' => $sum($exercicios, 'ok'),
                'provas' => $sum($provas, 'n'),
                'provas_acertos' => $provasOk,
                'provas_total' => $provasTotal,
                'nota' => $provasTotal ? round($provasOk / $provasTotal * 10, 1) : null,
                'minutos' => intdiv($sum($tempo, 's'), 60),
                'ultimo' => $ultimo ? Carbon::parse($ultimo) : null,
            ];
        })->values();
    }

    /**
     * Cursos concluídos por cliente: colaboradores que leram todas as aulas do
     * curso que o contrato do cliente libera. Conta quando a última aula foi
     * concluída dentro do período.
     *
     * @param  Collection<string, string>  $members  user_id => tenant_id
     * @return array<string, int> tenant_id => cursos concluídos
     */
    private function coursesDone(Collection $members, ?Carbon $since): array
    {
        if ($members->isEmpty()) {
            return [];
        }

        $lessons = Lesson::withoutGlobalScopes()->whereHas('course', fn ($q) => $q->withoutGlobalScopes()->where('is_published', true))
            ->get(['id', 'course_id', 'feature_key']);
        $tenants = Tenant::withoutGlobalScopes()->with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->whereIn('id', $members->unique()->values())->get()->keyBy('id');

        // aulas liberadas por cliente, agrupadas por curso
        $liberadas = [];
        foreach ($tenants as $tenant) {
            if (! $tenant->plan?->hasFeature('tabela_courses')) {
                continue;
            }
            $liberadas[$tenant->id] = $lessons
                ->filter(fn ($l) => ! $l->feature_key || $tenant->plan->hasFeature($l->feature_key))
                ->groupBy('course_id')
                ->map(fn ($grupo) => $grupo->pluck('id')->all())
                ->all();
        }

        $lidas = LessonProgress::withoutGlobalScopes()->whereIn('user_id', $members->keys())->whereNotNull('completed_at')
            ->get(['user_id', 'lesson_id', 'completed_at'])->groupBy('user_id');

        $out = [];
        foreach ($members as $userId => $tenantId) {
            $porCurso = $liberadas[$tenantId] ?? [];
            $doUsuario = ($lidas[$userId] ?? collect())->keyBy('lesson_id');

            foreach ($porCurso as $ids) {
                if ($ids === [] || ! collect($ids)->every(fn ($id) => $doUsuario->has($id))) {
                    continue;
                }

                $fim = collect($ids)->map(fn ($id) => Carbon::parse($doUsuario[$id]->completed_at))->max();
                if (! $since || $fim->gte($since)) {
                    $out[$tenantId] = ($out[$tenantId] ?? 0) + 1;
                }
            }
        }

        return $out;
    }

    /**
     * Acessos à Academia mês a mês (últimos $months meses), por cliente.
     *
     * @return array{months: list<array{key: string, label: string}>, rows: Collection<int, array<string, mixed>>}
     */
    public function monthly(int $months = 12, ?string $tenantId = null): array
    {
        $inicio = now()->startOfMonth()->subMonths($months - 1);
        $lista = collect(range(0, $months - 1))->map(fn ($i) => $inicio->copy()->addMonths($i))
            ->map(fn (Carbon $m) => ['key' => $m->format('Y-m'), 'label' => $m->translatedFormat('M/y')])->all();

        $dados = $this->logQuery($tenantId)->where('created_at', '>=', $inicio)
            ->selectRaw("tenant_id, to_char(created_at, 'YYYY-MM') as mes, count(*) as acessos, count(distinct user_id) as pessoas")
            ->groupBy('tenant_id', 'mes')->get()->groupBy('tenant_id');

        $rows = Tenant::withoutGlobalScopes()->when($tenantId, fn ($q) => $q->whereKey($tenantId))->orderBy('name')->get(['id', 'name'])
            ->map(function (Tenant $t) use ($dados, $lista) {
                $porMes = ($dados[$t->id] ?? collect())->keyBy('mes');

                return [
                    'cliente' => $t->name,
                    'meses' => collect($lista)->mapWithKeys(fn ($m) => [$m['key'] => ['acessos' => (int) ($porMes[$m['key']]->acessos ?? 0), 'pessoas' => (int) ($porMes[$m['key']]->pessoas ?? 0)]])->all(),
                ];
            })->values();

        return ['months' => $lista, 'rows' => $rows];
    }

    /**
     * Acessos por dia nos últimos $days dias (todos os clientes ou um só).
     *
     * @return Collection<int, array{dia: string, label: string, acessos: int, pessoas: int}>
     */
    public function daily(int $days = 30, ?string $tenantId = null): Collection
    {
        $inicio = now()->startOfDay()->subDays($days - 1);

        $dados = $this->logQuery($tenantId)->where('created_at', '>=', $inicio)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as dia, count(*) as acessos, count(distinct user_id) as pessoas")
            ->groupBy('dia')->get()->keyBy('dia');

        return collect(range(0, $days - 1))->map(function ($i) use ($inicio, $dados) {
            $d = $inicio->copy()->addDays($i);

            return [
                'dia' => $d->format('Y-m-d'),
                'label' => $d->format('d/m'),
                'acessos' => (int) ($dados[$d->format('Y-m-d')]->acessos ?? 0),
                'pessoas' => (int) ($dados[$d->format('Y-m-d')]->pessoas ?? 0),
            ];
        });
    }

    /** @return Builder */
    private function logQuery(?string $tenantId)
    {
        return DB::table('user_activity_logs')
            ->whereIn('user_id', $this->members($tenantId)->keys()->all())
            ->where('path', 'like', '/academia%')->where('action', 'view');
    }

    /**
     * Colaboradores de um cliente com o que cada um fez no período.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function users(string $tenantId, ?Carbon $since): Collection
    {
        $users = User::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->when($this->staff(), fn ($q) => $q->whereNotIn(DB::raw('lower(email)'), $this->staff()))
            ->orderBy('name')->get(['id', 'name', 'email']);
        $ids = $users->pluck('id')->all();
        $at = fn (string $col) => fn ($q) => $since ? $q->where($col, '>=', $since) : $q;

        $logs = DB::table('user_activity_logs')->whereIn('user_id', $ids)->where('path', 'like', '/academia%')->where('action', 'view')->tap($at('created_at'))
            ->selectRaw('user_id, count(*) as n, max(created_at) as ultimo')->groupBy('user_id')->get()->keyBy('user_id');
        $aulas = DB::table('lesson_progress')->whereIn('user_id', $ids)->whereNotNull('completed_at')->tap($at('completed_at'))
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->get()->keyBy('user_id');
        $exercicios = DB::table('lesson_answers')->whereIn('user_id', $ids)->tap($at('updated_at'))
            ->selectRaw('user_id, count(*) as n, sum(case when is_correct then 1 else 0 end) as ok')->groupBy('user_id')->get()->keyBy('user_id');
        $provas = DB::table('lesson_quiz_submissions')->whereIn('user_id', $ids)->tap($at('delivered_at'))
            ->selectRaw('user_id, count(*) as n, sum(correct_answers) as ok, sum(total_questions) as total')->groupBy('user_id')->get()->keyBy('user_id');

        return $users->map(fn (User $u) => [
            'nome' => $u->name,
            'email' => $u->email,
            'acessos' => (int) ($logs[$u->id]->n ?? 0),
            'ultimo' => isset($logs[$u->id]) ? Carbon::parse($logs[$u->id]->ultimo) : null,
            'aulas' => (int) ($aulas[$u->id]->n ?? 0),
            'exercicios' => (int) ($exercicios[$u->id]->n ?? 0),
            'exercicios_acertos' => (int) ($exercicios[$u->id]->ok ?? 0),
            'provas' => (int) ($provas[$u->id]->n ?? 0),
            'provas_acertos' => (int) ($provas[$u->id]->ok ?? 0),
            'provas_total' => (int) ($provas[$u->id]->total ?? 0),
        ])->values();
    }

    /**
     * Testes públicos de treinamento, por empresa (a do cliente cadastrado, ou o
     * nome digitado quando não bate com nenhum cliente).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function publicTests(?Carbon $since): Collection
    {
        return TreinamentoResultado::query()->with('tenant:id,name')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->get()
            ->groupBy(fn (TreinamentoResultado $r) => $r->tenant?->name ?? ($r->empresa ?: '(sem empresa)'))
            ->map(fn (Collection $grupo, string $empresa) => [
                'empresa' => $empresa,
                'cadastrado' => $grupo->contains(fn ($r) => $r->tenant_id),
                'pessoas' => $grupo->map(fn ($r) => mb_strtolower(trim($r->nome)))->unique()->count(),
                'tentativas' => $grupo->count(),
                'aprovados' => $grupo->where('aprovado', true)->map(fn ($r) => mb_strtolower(trim($r->nome)))->unique()->count(),
                'acertos' => (int) $grupo->sum('acertos'),
                'total' => (int) $grupo->sum('total'),
                'melhor_nota' => (float) $grupo->max('nota'),
                'ultimo' => $grupo->max('created_at'),
            ])
            ->sortByDesc('tentativas')
            ->values();
    }
}
