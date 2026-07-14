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

    protected $fillable = [
        'tenant_id',
        'user_id',
        'maintenance_order_id',
        'provider_name',
        'status',
        'approved_by_user_id',
        'approved_at',
        'requested_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

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
