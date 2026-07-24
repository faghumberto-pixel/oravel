<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de mudança de nível da Matriz ABC de um Ativo -- não existia
 * até aqui (AbcMatrix é um snapshot único por Ativo, sem trilha). Gravado
 * automaticamente por AbcMatrixObserver a cada create/update de AbcMatrix,
 * consumido pelo Histórico do Patrimônio (App\Filament\Pages\HistoricoPatrimonio)
 * como a fonte real do evento "criticidade".
 */
class AbcMatrixHistory extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_abc_matrix_histories';

    protected static ?string $saasPermissionSlug = 'auditoria_abc';

    protected static ?string $saasModuleLabel = 'Histórico de Matriz ABC';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'nivel_anterior',
        'nivel_novo',
        'changed_by_user_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
