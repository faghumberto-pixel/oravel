<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_materials';

    protected static ?string $saasPermissionSlug = 'material';

    protected static ?string $saasModuleLabel = 'Materiais';

    /**
     * Papel que recebe alerta de estoque baixo por filial
     * (App\Services\MaterialStockService) e aprova Pedido de Compra
     * (MaterialRequest, Fase 2) -- mesmo padrao de papel nomeado ja usado
     * em EquipmentReplacement::ROLE_LOGISTICA.
     */
    public const ROLE_GESTOR_SUPRIMENTOS = 'Gestor de Suprimentos';

    protected $fillable = [
        'tenant_id',
        'sku',
        'part_number',
        'barcode',
        'name',
        'brand_name',
        'material_category_id',
        'supplier_id',
        'supplier_type',
        'unit_cost',
        'last_purchase_price',
        'current_stock',
        'min_stock',
        'max_stock',
        'unit_of_measure',
        'warehouse_location',
        'requires_serial_number',
        'is_remanufactured',
        'warranty_days',
        'ncm',
        'price',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'price' => 'decimal:2',
        'requires_serial_number' => 'boolean',
        'is_remanufactured' => 'boolean',
        'warranty_days' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Grupos de Ativo (Gerador, Compressor, etc.) compativeis com esta peca
     * -- usado pra sugerir materiais ao registrar uma Manutencao Preventiva.
     */
    public function checklistGroups(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistGroup::class, 'material_checklist_group');
    }

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->min_stock;
    }

    /**
     * Historico formal de entrada/saida de estoque (ver App\Models\StockMovement).
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Saldo por filial (ver App\Models\MaterialLocationStock) -- fonte de
     * verdade do estoque dali pra frente. current_stock (acima) continua
     * existindo como cache, somado daqui por App\Services\MaterialStockService.
     */
    public function locationStocks(): HasMany
    {
        return $this->hasMany(MaterialLocationStock::class);
    }

    /**
     * Recalcula o cache current_stock como soma de todas as filiais --
     * chamado por MaterialStockService toda vez que uma linha de
     * material_location_stock muda, pra nao quebrar leitura ja existente
     * (isLowStock(), coloracao de tabela do MaterialResource, gatilho de
     * PartsRequest) sem precisar reescrever cada ponto de leitura.
     */
    public function recalculateCurrentStock(): void
    {
        $this->updateQuietly([
            'current_stock' => (int) $this->locationStocks()->sum('current_quantity'),
        ]);
    }
}
