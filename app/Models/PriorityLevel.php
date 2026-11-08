<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalogo de prioridades da O.S. por tenant. Os 4 niveis do sistema (critica/alta/media/baixa) nascem com o tenant;
 * o tenant acrescenta os dele, sempre apontando para um nivel base (relatorios comparaveis entre clientes).
 */
class PriorityLevel extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_priority_levels';

    protected static ?string $saasPermissionSlug = 'nivel_prioridade';

    protected static ?string $saasModuleLabel = 'Níveis de Prioridade';

    public const BASE_LEVELS = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'];

    protected $fillable = ['tenant_id', 'code', 'name', 'color', 'base_level', 'sort_order', 'is_system', 'is_active'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Cor semantica do badge do Filament a partir do nivel base. */
    public function badgeColor(): string
    {
        return match ($this->base_level) {
            'critica' => 'danger',
            'alta' => 'warning',
            'media' => 'info',
            default => 'gray',
        };
    }
}
