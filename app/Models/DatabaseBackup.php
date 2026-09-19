<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de um backup diario do banco (scripts/backup-prod-database.sh).
 * Global, nao tenant-scoped -- um dump cobre todos os tenants de uma vez.
 */
class DatabaseBackup extends Model
{
    use HasUuids;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
        'path',
        'size_bytes',
        'tenant_count',
        'tenant_names',
        'status',
        'error_message',
    ];

    protected $casts = [
        'tenant_names' => 'array',
        'size_bytes' => 'integer',
        'tenant_count' => 'integer',
    ];

    public function includesTenant(string $tenantName): bool
    {
        return in_array($tenantName, $this->tenant_names ?? [], true);
    }
}
