<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contrato de fornecimento -- vinculo opcional de um fornecedor a um
 * contrato com vigencia. Nao reaproveita App\Models\Contract (aquele e'
 * moldado pra locacao a cliente, conceito diferente).
 */
class SupplierContract extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_supplier_contracts';

    protected static ?string $saasPermissionSlug = 'contrato_fornecedor';

    protected static ?string $saasModuleLabel = 'Contratos de Fornecimento';

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_ENCERRADO = 'encerrado';

    public const STATUS_SUSPENSO = 'suspenso';

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'contract_number',
        'object',
        'start_date',
        'end_date',
        'value',
        'payment_terms',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'decimal:2',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ATIVO => 'Ativo',
            self::STATUS_ENCERRADO => 'Encerrado',
            self::STATUS_SUSPENSO => 'Suspenso',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
