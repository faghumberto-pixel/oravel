<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abastecimento de um veículo. Histórico: não se edita nem se apaga (o consumo calculado depende da ordem). */
class FrotaAbastecimento extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_abastecimentos';

    protected static ?string $saasPermissionSlug = 'abastecimento_frota';

    protected static ?string $saasModuleLabel = 'Abastecimentos da Frota';

    /** Consumo abaixo de 85% da média das últimas 5 = atenção; abaixo de 70% = crítica. Precisa de 3 consumos anteriores. */
    public const DESVIO_ATENCAO = 0.85;

    public const DESVIO_CRITICO = 0.70;

    public const JANELA_REFERENCIA = 5;

    public const MINIMO_REFERENCIA = 3;

    protected $table = 'frota_abastecimentos';

    protected $attributes = ['tanque_cheio' => true, 'origem' => 'externo'];

    protected $fillable = [
        'tenant_id', 'ativo_id', 'motorista_id', 'abastecido_em', 'odometro', 'combustivel', 'litros', 'valor_litro', 'valor_total',
        'tanque_cheio', 'origem', 'posto', 'nota_fiscal', 'km_rodado', 'consumo_km_l', 'observacoes', 'registrado_por', 'peca_id', 'almoxarifado_id',
    ];

    protected $casts = [
        'abastecido_em' => 'datetime', 'odometro' => 'integer', 'litros' => 'decimal:2', 'valor_litro' => 'decimal:3', 'valor_total' => 'decimal:2',
        'tanque_cheio' => 'boolean', 'km_rodado' => 'integer', 'consumo_km_l' => 'decimal:2',
    ];

    /** @return array<string, string> */
    public static function combustivelLabels(): array
    {
        return ['diesel_s10' => 'Diesel S10', 'diesel_s500' => 'Diesel S500', 'gasolina' => 'Gasolina', 'etanol' => 'Etanol', 'gnv' => 'GNV', 'outro' => 'Outro'];
    }

    /** @return array<string, string> */
    public static function origemLabels(): array
    {
        return ['externo' => 'Posto (externo)', 'tanque_proprio' => 'Tanque próprio'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }
}
