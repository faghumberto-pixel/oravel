<?php

namespace App\Http\Middleware;

use App\Filament\Resources\CourseResource;
use App\Models\Course;
use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada das telas /academia (fora do painel Filament, entao nao herda o middleware
 * dele). Quem tem cadastro no app entra: exige login (e cadastro aprovado), respeita o bloqueio
 * por inadimplencia e o modulo Academia ligado no contrato. NAO exige permissao especifica em
 * Perfis de Acesso -- a Academia e' pra toda a equipe.
 */
class EnsureAcademyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest('/admin/login');
        }

        abort_unless(CourseResource::canViewAny(), 403);

        if (! $user->isSuperAdmin() && $user->tenant?->isAccessBlockedForNonPayment()) {
            return redirect()->route('admin.conta-bloqueada');
        }

        $response = $next($request);
        $this->logVisit($request, $user);

        return $response;
    }

    /**
     * Registra a entrada em cada tela da Academia no mesmo log de atividade do resto do app
     * (aparece em Central > Acessos dos Clientes). Só GET de página; chamadas Livewire de fundo
     * (como a batida de tempo) não entram — o tempo de estudo vem de academy_points.seconds.
     */
    private function logVisit(Request $request, $user): void
    {
        if (! $request->isMethod('GET') || $request->hasHeader('X-Livewire') || ! $user->tenant_id) {
            return;
        }

        $label = match ($request->route()?->getName()) {
            'academy.home' => 'Academia — Início',
            'academy.ranking' => 'Academia — Ranking',
            'academy.team' => 'Academia — Equipe',
            'academy.course' => 'Academia — '.(Course::withoutGlobalScopes()->where('slug', $request->route('slug'))->value('title') ?? 'Curso'),
            default => 'Academia',
        };

        UserActivityLog::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'method' => 'GET',
            'path' => mb_substr('/'.trim($request->path(), '/'), 0, 191),
            'route_name' => $request->route()?->getName(),
            'resource_label' => mb_substr($label, 0, 191),
            'action' => UserActivityLog::ACTION_VIEW,
        ]);
    }
}
