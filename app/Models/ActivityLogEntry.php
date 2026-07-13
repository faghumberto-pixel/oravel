<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Spatie\Activitylog\Models\Activity;

/**
 * Subclass fina de Spatie\Activitylog\Models\Activity (tabela
 * `activity_log`, ja gravada de verdade por Asset/EquipmentDamage/CrmLead
 * via LogsActivity::logOnlyDirty()) so pra poder aplicar HasSaaSMetadata
 * -- vira modulo real no SaaSRegistry, sem mudar como o log e gravado
 * (continua sendo a classe Activity do Spatie que grava, configuravel em
 * config/activitylog.php).
 *
 * Sem BelongsToTenant de proposito: tabela nao tem tenant_id, o
 * isolamento acontece por subject (Asset/EquipmentDamage/CrmLead, que ja
 * usam BelongsToTenant) -- ver ActivityLogResource::getEloquentQuery(),
 * que usa whereHasMorph('subject', [...]) e ganha o escopo de graca.
 */
class ActivityLogEntry extends Activity
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_activity_log_entries';

    protected static ?string $saasPermissionSlug = 'auditoria_alteracao';

    protected static ?string $saasModuleLabel = 'Auditoria de Alterações';
}
