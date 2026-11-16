<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma chamada a API de IA (tokens e custo estimado). Dado de PLATAFORMA, lido so' pela Central (super admin):
 * por isso nao usa BelongsToTenant -- o tenant_id aqui e' o cliente a quem a chamada foi atribuida
 * (ou nulo: Prospeccao, WhatsApp, jobs). Nao entra em nenhum painel de cliente.
 */
class IaConsumo extends Model
{
    use HasUuids;
    use Prunable;

    public const UPDATED_AT = null;

    protected $table = 'ia_consumos';

    protected $fillable = ['tenant_id', 'user_id', 'area', 'etapa', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'custo_usd'];

    protected function casts(): array
    {
        return ['custo_usd' => 'float', 'created_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('ia.retencao_dias', 400)));
    }
}
