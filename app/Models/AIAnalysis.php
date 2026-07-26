<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de cada chamada feita a uma IA externa (Claude) -- guarda o
 * contexto enviado e a resposta recebida, pra auditoria e pra nao perder
 * a analise se o usuario recarregar a pagina. Um tipo por caso de uso
 * (avaria/comercial/estoque/logistica/retrabalho/plano_preventivo). Nao e'
 * morph generico -- tipos com um registro "dono" unico ganham FK propria
 * (equipment_damage_id, crm_lead_id); tipos tenant-wide (estoque,
 * retrabalho, plano_preventivo) nao tem FK, fica tudo em context/response.
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

    public const TYPE_RETRABALHO = 'retrabalho';

    public const TYPE_PLANO_PREVENTIVO = 'plano_preventivo';

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
        'crm_lead_id',
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

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    /**
     * Rotulo de "do que trata" essa analise, usado na listagem e na tela
     * de detalhe da Central de IA. Cada tipo referencia algo diferente
     * (avaria -> ativo, comercial -> lead, logistica -> uma data, nao um
     * registro so'), por isso nao da' pra usar uma unica FK generica.
     */
    public function getReferenceLabelAttribute(): ?string
    {
        return match ($this->type) {
            self::TYPE_AVARIA => $this->equipmentDamage?->asset?->name,
            // Lead pode ter sido excluido depois da analise; cai pro nome
            // que ja estava salvo no context original nesse caso.
            self::TYPE_COMERCIAL => $this->crmLead?->name ?? data_get($this->context, 'lead.nome'),
            self::TYPE_LOGISTICA => filled(data_get($this->context, 'data'))
                ? Carbon::parse(data_get($this->context, 'data'))->format('d/m/Y')
                : null,
            default => null,
        };
    }
}
