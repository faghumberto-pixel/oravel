<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lavagem ou limpeza de um veículo, com agenda opcional (repetir a cada N dias). Histórico: não se edita nem se apaga. */
class FrotaLavagem extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_lavagens';

    protected static ?string $saasPermissionSlug = 'lavagem_frota';

    protected static ?string $saasModuleLabel = 'Lavagens da Frota';

    protected $table = 'frota_lavagens';

    /** @var array<string, string> */
    public const TIPOS = ['simples' => 'Simples', 'completa' => 'Completa', 'higienizacao' => 'Higienização', 'motor' => 'Motor', 'outro' => 'Outro'];

    protected $fillable = ['tenant_id', 'ativo_id', 'realizada_em', 'tipo', 'valor', 'local', 'intervalo_dias', 'proxima_prevista', 'observacoes', 'registrado_por'];

    protected $casts = ['realizada_em' => 'date', 'proxima_prevista' => 'date', 'valor' => 'decimal:2', 'intervalo_dias' => 'integer'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
