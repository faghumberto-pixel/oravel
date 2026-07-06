<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbcMatrix extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;

    // Sem HasUuids: id e bigint auto-incremento de verdade (nao uuid) --
    // com HasUuids, AbcMatrix::create() sempre quebrava tentando inserir um
    // uuid numa coluna bigint (tabela ficou vazia desde sempre por causa
    // disso).
    protected static ?string $saasFeatureKey = 'tabela_abc_matrix';

    protected static ?string $saasPermissionSlug = 'matriz_abc';

    protected static ?string $saasModuleLabel = 'Matriz ABC';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'nivel',
        'descricao',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
