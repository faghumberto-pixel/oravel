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

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }
}
