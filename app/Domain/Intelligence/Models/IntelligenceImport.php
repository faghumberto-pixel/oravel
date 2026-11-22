<?php

namespace App\Domain\Intelligence\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Um envio de dados da empresa. Cada importação concluída gera exatamente um snapshot. */
class IntelligenceImport extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'intelligence_imports';

    protected static ?string $saasFeatureKey = 'tabela_intelligence_imports';

    protected static ?string $saasPermissionSlug = 'intelligence_import';

    protected static ?string $saasModuleLabel = 'Intelligence - Importações';

    protected $fillable = ['tenant_id', 'user_id', 'origem', 'arquivo', 'status', 'erro', 'concluida_em'];

    protected $casts = ['concluida_em' => 'datetime'];

    public function snapshot(): HasOne
    {
        return $this->hasOne(IntelligenceSnapshot::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
