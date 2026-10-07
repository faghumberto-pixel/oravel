<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baixa (venda, sucata, perda total...) de um veículo da frota. Sem reversão = o veículo está baixado. */
class FrotaBaixa extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_baixas';

    protected static ?string $saasPermissionSlug = 'baixa_frota';

    protected static ?string $saasModuleLabel = 'Baixa de Veículos da Frota';

    protected $table = 'frota_baixas';

    protected $fillable = ['tenant_id', 'ativo_id', 'tipo', 'data', 'valor', 'comprador', 'documento', 'odometro_final', 'motivo', 'revertida_em', 'motivo_reversao', 'registrado_por'];

    protected $casts = ['data' => 'date', 'valor' => 'decimal:2', 'odometro_final' => 'integer', 'revertida_em' => 'datetime'];

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return ['venda' => 'Venda', 'sucata' => 'Sucata', 'perda_total' => 'Perda total', 'doacao' => 'Doação', 'outro' => 'Outro'];
    }

    public function vigente(): bool
    {
        return $this->revertida_em === null;
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
