<?php

namespace App\Services;

use App\Filament\Central\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;

/**
 * Linha do tempo permanente da relação cliente x Oravel (Central). Cada fato
 * (assinou, pagou, atrasou...) vira um TenantEvent; os importantes também
 * tocam o sino dos super admins. Nunca lança: registrar histórico não pode
 * derrubar assinatura, webhook nem cadastro.
 */
class TenantTimeline
{
    private static ?bool $tableExists = null;

    /**
     * @param  array<string, mixed>  $properties
     * @param  string|null  $dedupeKey  mesma chave => mesmo fato, registrado uma vez (webhooks reenviados)
     * @param  string  $level  success|danger|warning|info (cor do aviso do sino)
     */
    public static function record(
        Tenant|string $tenant,
        string $type,
        string $title,
        ?string $description = null,
        array $properties = [],
        ?string $dedupeKey = null,
        bool $notify = false,
        string $level = 'info',
        ?Carbon $at = null,
    ): ?TenantEvent {
        try {
            if (! self::tableExists()) {
                return null;
            }

            $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::withoutGlobalScopes()->find($tenant);

            if (! $tenantModel) {
                return null;
            }

            if ($dedupeKey && TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenantModel->id)->where('dedupe_key', $dedupeKey)->exists()) {
                return null;
            }

            $event = TenantEvent::withoutGlobalScopes()->create([
                'tenant_id' => $tenantModel->id,
                'event_type' => $type,
                'title' => $title,
                'description' => $description,
                'properties' => $properties ?: null,
                'dedupe_key' => $dedupeKey,
                'actor_user_id' => auth()->id(),
                'occurred_at' => $at ?? now(),
            ]);

            if ($notify) {
                self::notifyCentral($tenantModel, $title, $description, $level);
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning('TenantTimeline: falha ao registrar evento.', ['type' => $type, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Sino de notificações do painel /central, para os super admins. */
    public static function notifyCentral(Tenant $tenant, string $title, ?string $body, string $level = 'info'): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon(match ($level) {
                'success' => 'heroicon-o-check-badge',
                'danger' => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-bell',
            })
            ->iconColor($level)
            ->actions([
                Action::make('open')->label('Abrir empresa')->url(TenantResource::getUrl('edit', ['record' => $tenant], panel: 'central')),
            ]);

        $recipients = User::withoutGlobalScopes()
            ->whereIn(DB::raw('lower(email)'), array_map('strtolower', config('oravel.super_admins', [])))
            ->get();

        foreach ($recipients as $recipient) {
            // sendNow: não depende de worker de fila.
            NotificationFacade::sendNow($recipient, $notification->toDatabase());
        }
    }

    private static function tableExists(): bool
    {
        return self::$tableExists ??= Schema::hasTable('tenant_events');
    }
}
