<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Onde um item esta (e quanto ha ali): material, peca/oleo ou item agregado em um endereco do almoxarifado. */
class StockPlacement extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    /** Uma chave de contrato so' para todo o "Estoque estruturado" (grupos, enderecos, alocacoes e contagens): a Central liga junto. */
    protected static ?string $saasFeatureKey = 'tabela_stock_structure';

    protected static ?string $saasPermissionSlug = 'alocacao_estoque';

    protected static ?string $saasModuleLabel = 'Itens por Endereço';

    public const MATERIAL = 'material';

    public const PART = 'part';

    public const AGGREGATE = 'aggregate';

    public const TIPOS = [self::MATERIAL => 'Material', self::PART => 'Peça / óleo', self::AGGREGATE => 'Item agregado'];

    protected $fillable = ['tenant_id', 'stock_location_id', 'item_type', 'item_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    /** Nome do item (material, peca ou tipo de item agregado) para mostrar nas telas. */
    public function nomeDoItem(): string
    {
        return match ($this->item_type) {
            self::MATERIAL => Material::query()->whereKey($this->item_id)->value('name'),
            self::PART => Part::query()->whereKey($this->item_id)->value('name'),
            self::AGGREGATE => AggregateItemType::query()->whereKey($this->item_id)->value('name'),
            default => null,
        } ?? 'Item removido';
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }
}
