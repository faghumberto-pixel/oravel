<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Intelligence\Concerns\Imutavel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fotografia da empresa numa data. Imutável: dado novo vira snapshot novo. */
class IntelligenceSnapshot extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use Imutavel;

    protected $table = 'intelligence_snapshots';

    protected static ?string $saasFeatureKey = 'tabela_intelligence_snapshots';

    protected static ?string $saasPermissionSlug = 'intelligence_snapshot';

    protected static ?string $saasModuleLabel = 'Intelligence - Snapshots';

    protected $fillable = ['tenant_id', 'import_id', 'data_referencia', 'dados', 'hash', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'data_referencia' => 'date', 'dados' => 'array'];

    public function indicators(): HasMany
    {
        return $this->hasMany(IntelligenceIndicator::class, 'snapshot_id');
    }

    public function diagnostics(): HasMany
    {
        return $this->hasMany(IntelligenceDiagnostic::class, 'snapshot_id');
    }

    public function equipamentos(): HasMany
    {
        return $this->hasMany(IntelligenceEquipamento::class, 'snapshot_id');
    }

    public function analisesDeEquipamentos(): HasMany
    {
        return $this->hasMany(IntelligenceEquipamentoAnalise::class, 'snapshot_id');
    }
}
