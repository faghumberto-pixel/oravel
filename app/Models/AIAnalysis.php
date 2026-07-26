<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada chamada feita a uma IA externa (Claude) -- guarda o
 * contexto enviado e a resposta recebida, pra auditoria e pra nao perder
 * a analise se o usuario recarregar a pagina. Um tipo por caso de uso
 * (avaria/comercial/estoque/logistica); a v1 (2026-07-25) so' implementa
 * 'avaria'.
 */
class AIAnalysis extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const TYPE_AVARIA = 'avaria';

    public const TYPE_COMERCIAL = 'comercial';

    public const TYPE_ESTOQUE = 'estoque';

    public const TYPE_LOGISTICA = 'logistica';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_CONCLUIDA = 'concluida';

    public const STATUS_FALHOU = 'falhou';

    protected static ?string $saasFeatureKey = 'ia_diagnostico_avarias';

    protected static ?string $saasPermissionSlug = 'analise_ia';

    protected static ?string $saasModuleLabel = 'IA - Diagnóstico de Avarias';

    // Sem isso, Eloquent adivinha "a_i_analyses" (Str::snake insere "_"
    // entre cada letra maiuscula consecutiva de "AIAnalysis"), nao
    // "ai_analyses" como a migration realmente criou.
    protected $table = 'ai_analyses';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'equipment_damage_id',
        'context',
        'response',
        'status',
        'error',
        'action_taken',
    ];

    protected $casts = [
        'context' => 'array',
        'response' => 'array',
        'action_taken' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function equipmentDamage(): BelongsTo
    {
        return $this->belongsTo(EquipmentDamage::class);
    }
}
