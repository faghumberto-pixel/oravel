<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Mão de obra especializada que acompanha a locação (operador, engenharia, mobilização). */
class SpecializedService extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_OPERADOR = 'operador';

    public const TYPE_ENGENHARIA = 'engenharia';

    public const TYPE_MOBILIZACAO = 'mobilizacao';

    protected static ?string $saasFeatureKey = 'tabela_specialized_services';

    protected static ?string $saasPermissionSlug = 'mao_de_obra_especializada';

    protected static ?string $saasModuleLabel = 'Mão de Obra Especializada';

    protected $fillable = [
        'tenant_id', 'service_type', 'title', 'employee_id', 'supplier_id', 'asset_id', 'contract_id',
        'start_date', 'end_date', 'cost', 'status', 'notes',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'cost' => 'decimal:2'];

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_OPERADOR => 'Operador / técnico de plantão',
            self::TYPE_ENGENHARIA => 'Engenharia e planejamento',
            self::TYPE_MOBILIZACAO => 'Mobilização e desmobilização',
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return ['planejado' => 'Planejado', 'em_andamento' => 'Em andamento', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
