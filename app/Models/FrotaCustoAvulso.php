<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Custo avulso de um veículo (seguro, IPVA, licenciamento, pedágio...), com rateio em meses. */
class FrotaCustoAvulso extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_custos_avulsos';

    protected static ?string $saasPermissionSlug = 'custo_avulso_frota';

    protected static ?string $saasModuleLabel = 'Custos Avulsos da Frota';

    protected $table = 'frota_custos_avulsos';

    protected $attributes = ['rateio_meses' => 1];

    protected $fillable = ['tenant_id', 'ativo_id', 'tipo', 'data', 'valor', 'rateio_meses', 'descricao', 'registrado_por'];

    protected $casts = ['data' => 'date', 'valor' => 'decimal:2', 'rateio_meses' => 'integer'];

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [
            'seguro' => 'Seguro', 'ipva' => 'IPVA', 'licenciamento' => 'Licenciamento', 'tacografo' => 'Tacógrafo',
            'pedagio' => 'Pedágio', 'lavagem' => 'Lavagem', 'estacionamento' => 'Estacionamento', 'outro' => 'Outro',
        ];
    }

    /** Parte deste custo que cai dentro dos meses [início, fim] (cada mês do rateio vale valor ÷ meses). */
    public function parteNoPeriodo(Carbon $inicio, Carbon $fim): float
    {
        $meses = max(1, $this->rateio_meses);
        $primeiro = $this->data->copy()->startOfMonth();
        $de = $inicio->copy()->startOfMonth();
        $ate = $fim->copy()->startOfMonth();
        $dentro = 0;
        for ($i = 0; $i < $meses; $i++) {
            $mes = $primeiro->copy()->addMonthsNoOverflow($i);
            if ($mes->gte($de) && $mes->lte($ate)) {
                $dentro++;
            }
        }

        return round((float) $this->valor / $meses * $dentro, 2);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
