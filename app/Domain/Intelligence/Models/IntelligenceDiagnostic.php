<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Intelligence\Concerns\Imutavel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Saúde da empresa por área (e índice geral) num snapshot. Áreas em config/intelligence.php. */
class IntelligenceDiagnostic extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use Imutavel;

    protected $table = 'intelligence_diagnostics';

    protected static ?string $saasFeatureKey = 'tabela_intelligence_diagnostics';

    protected static ?string $saasPermissionSlug = 'intelligence_diagnostic';

    protected static ?string $saasModuleLabel = 'Intelligence - Diagnósticos';

    protected $fillable = ['tenant_id', 'snapshot_id', 'area', 'pontuacao', 'faixa', 'detalhes', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'pontuacao' => 'decimal:2', 'detalhes' => 'array'];
}
