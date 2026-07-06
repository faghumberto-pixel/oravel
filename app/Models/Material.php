<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Material extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_materials';

    protected static ?string $saasPermissionSlug = 'material';

    protected static ?string $saasModuleLabel = 'Materiais';

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
}
