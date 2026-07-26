<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Requisicao de compra formal (multi-item, com fluxo de aprovacao) --
 * ate 2026-07-14 esse desenho existia no banco mas nunca tinha sido
 * ligado a nenhuma tela (zero linha criada em producao, so' uma leitura
 * defensiva num Chat de IA). Convive com App\Models\PartsRequest (o
 * alerta automatico de estoque baixo, 1 material, sem aprovacao) -- ver
 * PartsRequest::convertedToMaterialRequest().
 */
class MaterialRequest extends Model
{
    use BelongsToTenant, HasSaaSMetadata, HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_material_requests';

    protected static ?string $saasPermissionSlug = 'requisicao_compra';

    protected static ?string $saasModuleLabel = 'Requisições de Compra';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_AGUARDANDO_APROVACAO = 'aguardando_aprovacao';

    public const STATUS_APROVADA = 'aprovado';

    public const STATUS_RECUSADA = 'recusado';

    public const STATUS_COTADO = 'cotado';

    public const STATUS_A_CAMINHO = 'a_caminho';

    public const STATUS_ENTREGUE = 'entregue';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_URGENTE = 'urgente';

    public const PRIORITY_CRITICO = 'critico';

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_ORDEM_SERVICO = 'ordem_servico';

    public const ORIGIN_REPOSICAO_ESTOQUE = 'reposicao_estoque';

    public const ORIGIN_CONVERSAO_PARTS_REQUEST = 'conversao_parts_request';

    /**
     * Papel que aprova/recusa Pedido de Compra e recebe alerta de estoque
     * baixo -- ver Material::ROLE_GESTOR_SUPRIMENTOS (mesma constante,
     * so' reexposta aqui pra quem so' conhece MaterialRequest).
     */
    public const ROLE_GESTOR_SUPRIMENTOS = Material::ROLE_GESTOR_SUPRIMENTOS;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'maintenance_order_id',
        'origin',
        'requested_for_location_id',
        'provider_name',
        'priority',
        'target_delivery_date',
        'status',
        'approved_by_user_id',
        'approved_at',
        'requested_at',
        'delivered_at',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
        'approved_at' => 'datetime',
        'target_delivery_date' => 'date',
    ];

    protected $attributes = [
        'priority' => self::PRIORITY_NORMAL,
    ];

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_URGENTE => 'Urgente',
            self::PRIORITY_CRITICO => 'Crítico',
        ];
    }

    public static function originOptions(): array
    {
        return [
            self::ORIGIN_MANUAL => 'Manual',
            self::ORIGIN_ORDEM_SERVICO => 'Ordem de Serviço',
            self::ORIGIN_REPOSICAO_ESTOQUE => 'Reposição de Estoque',
            self::ORIGIN_CONVERSAO_PARTS_REQUEST => 'Conversão de Solicitação de Peças',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_AGUARDANDO_APROVACAO => 'Aguardando Aprovação',
            self::STATUS_APROVADA => 'Aprovada',
            self::STATUS_RECUSADA => 'Recusada',
            self::STATUS_COTADO => 'Cotada',
            self::STATUS_A_CAMINHO => 'A Caminho',
            self::STATUS_ENTREGUE => 'Entregue',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function requestedForLocation(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class, 'requested_for_location_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(MaterialRequestQuotation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
