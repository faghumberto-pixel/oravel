<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Política de SLA do tenant: para cada prioridade (e, opcionalmente, tipo de OS), os prazos em minutos de cada relógio. */
class SlaPolicy extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_sla_policies';

    protected static ?string $saasPermissionSlug = 'politica_sla';

    protected static ?string $saasModuleLabel = 'Políticas de SLA';

    protected $fillable = ['tenant_id', 'priority_code', 'maintenance_type', 'client_id', 'contract_id', 'response_minutes', 'service_minutes', 'resolution_minutes', 'is_active'];

    protected $casts = [
        'response_minutes' => 'integer',
        'service_minutes' => 'integer',
        'resolution_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return array<string, int> relógio => minutos (só os definidos) */
    public function minutosPorRelogio(): array
    {
        return array_filter([
            SlaTimer::RESPONSE => $this->response_minutes,
            SlaTimer::SERVICE => $this->service_minutes,
            SlaTimer::RESOLUTION => $this->resolution_minutes,
        ], fn ($m) => $m !== null && $m > 0);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
