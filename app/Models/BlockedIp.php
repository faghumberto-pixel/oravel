<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IPs bloqueados globalmente (cross-tenant, gerenciado só pelo painel
 * Central) -- pedido do usuário 2026-09-27: botão "Bloquear IP" na tela
 * de Acessos e Visitantes. Enforcement real em
 * App\Http\Middleware\BlockBannedIps, registrado bem cedo na pilha
 * global (bootstrap/app.php) pra barrar antes de qualquer outra lógica.
 */
class BlockedIp extends Model
{
    use HasUuids;

    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by_user_id',
    ];

    /**
     * Motivo pelo qual NAO se pode bloquear este IP (ou null se pode). Bloquear o proprio IP, ou o de alguem da equipe/cliente que
     * entra logado, derruba o acesso a TODO o sistema (o bloqueio roda antes do login e o desbloqueio so existe na Central).
     */
    public static function motivoParaNaoBloquear(string $ip, ?string $ipDeQuemBloqueia = null): ?string
    {
        if ($ipDeQuemBloqueia !== null && $ip === $ipDeQuemBloqueia) {
            return 'Este é o seu próprio IP. Bloqueá-lo tiraria você do sistema.';
        }
        if (SiteVisit::where('ip_address', $ip)->whereNotNull('user_id')->exists()) {
            return 'Este IP já foi usado por um usuário logado no sistema (equipe ou cliente). Bloqueá-lo tiraria o acesso dele.';
        }

        return null;
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }
}
