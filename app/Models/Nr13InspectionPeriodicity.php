<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * De-para configurável (tipo_equipamento + categoria_risco -> intervalo em meses), editável
 * pelo tenant. NÃO é uma tabela legal fixa: a periodicidade real de inspeção por NR-13 varia
 * por categoria de risco (PV pra caldeiras, grupo de potencial de risco pra vasos) e pode ser
 * estendida via Plano de Inspeção baseado em risco (PCPI/RBI), sob responsabilidade de
 * profissional habilitado -- este model só guarda o valor que o tenant configurou como padrão
 * pra sugerir a próxima inspeção; a decisão técnica continua sendo do responsável do tenant.
 */
class Nr13InspectionPeriodicity extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'nr13_inspection_periodicities';

    protected static ?string $saasFeatureKey = 'tabela_nr13_inspection_periodicities';

    protected static ?string $saasPermissionSlug = 'periodicidade_nr13';

    protected static ?string $saasModuleLabel = 'Periodicidade de Inspeção NR-13';

    protected $fillable = [
        'tenant_id',
        'tipo_equipamento',
        'categoria_risco',
        'intervalo_meses',
    ];

    protected $casts = [
        'intervalo_meses' => 'integer',
    ];
}
