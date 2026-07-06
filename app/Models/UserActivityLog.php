<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_user_activity_logs';

    protected static ?string $saasPermissionSlug = 'log_atividade';

    protected static ?string $saasModuleLabel = 'Logs de Atividade';

    public const ACTION_VIEW = 'view';

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_LOGIN = 'login';

    public const ACTION_LOGOUT = 'logout';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'method',
        'path',
        'route_name',
        'resource_label',
        'action',
        'subject_type',
        'subject_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
