<?php

namespace App\Filament\Central\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\AccessAnalytics;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Quem, de cada cliente, entrou no sistema, onde foi e por quanto tempo (estimado).
 * Lê user_activity_logs (logins, telas abertas, criações/edições) — ver AccessAnalytics.
 */
class AcessosClientes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Acessos dos Clientes';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $title = 'Acessos dos Clientes';

    protected static string $view = 'filament.central.pages.acessos-clientes';

    public ?string $tenantId = null;

    public string $period = '7';

    public bool $showStaff = false;

    public ?string $selectedUserId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['tenantId', 'period', 'showStaff'], true)) {
            $this->selectedUserId = null;
        }
    }

    public function select(string $userId): void
    {
        $this->selectedUserId = $this->selectedUserId === $userId ? null : $userId;
    }

    /** @return Collection<int, Tenant> */
    public function tenants(): Collection
    {
        return Tenant::query()->orderBy('name')->get(['id', 'name']);
    }

    private function since(): Carbon
    {
        return $this->period === '1' ? now()->startOfDay() : now()->subDays((int) $this->period)->startOfDay();
    }

    /** @return Collection<int, UserActivityLog> */
    private function logs(?string $userId = null): Collection
    {
        return UserActivityLog::withoutGlobalScopes()
            ->where('created_at', '>=', $this->since())
            ->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('created_at')
            ->get(['user_id', 'tenant_id', 'action', 'resource_label', 'path', 'created_at']);
    }

    /**
     * @return Collection<int, array<string, mixed>> um por usuário, o mais recente primeiro
     */
    public function rows(): Collection
    {
        $logs = $this->logs()->groupBy('user_id');
        if ($logs->isEmpty()) {
            return collect();
        }

        $users = User::withoutGlobalScopes()->whereIn('id', $logs->keys())->with('tenant:id,name')->get()->keyBy('id');
        $staff = array_map('strtolower', (array) config('oravel.super_admins'));

        return $logs->map(function ($userLogs, $userId) use ($users, $staff) {
            $user = $users[$userId] ?? null;
            $isStaff = $user && in_array(strtolower((string) $user->email), $staff, true);
            $sessions = AccessAnalytics::sessions(AccessAnalytics::fromLogs($userLogs));

            return [
                'user_id' => $userId,
                'name' => $user?->name ?? '(usuário removido)',
                'email' => $user?->email,
                'tenant' => $user?->tenant?->name,
                'staff' => $isStaff,
                'online' => (bool) $user?->isOnline(),
                'summary' => AccessAnalytics::summary($sessions),
            ];
        })
            ->reject(fn ($r) => $r['staff'] && ! $this->showStaff)
            ->sortByDesc(fn ($r) => [(int) $r['online'], $r['summary']['last_at']?->getTimestamp() ?? 0])
            ->values();
    }

    /** @return array<string, mixed>|null sessões do usuário selecionado */
    public function detail(): ?array
    {
        if (! $this->selectedUserId) {
            return null;
        }
        $sessions = AccessAnalytics::sessions(AccessAnalytics::fromLogs($this->logs($this->selectedUserId)));

        return ['sessions' => $sessions, 'summary' => AccessAnalytics::summary($sessions)];
    }
}
