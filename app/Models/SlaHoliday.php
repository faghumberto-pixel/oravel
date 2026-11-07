<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Feriado (ou dia sem expediente) do tenant: no calendário comercial do SLA o relógio não corre nesse dia. */
class SlaHoliday extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_sla_holidays';

    protected static ?string $saasPermissionSlug = 'feriado_sla';

    protected static ?string $saasModuleLabel = 'Feriados do SLA';

    protected $fillable = ['tenant_id', 'date', 'name'];

    protected $casts = ['date' => 'date'];
}
