<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modelo de checklist da frota (rápido ou completo), com seus itens. */
class FrotaModeloChecklist extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_modelos_checklist';

    protected static ?string $saasPermissionSlug = 'modelo_checklist_frota';

    protected static ?string $saasModuleLabel = 'Modelos de Checklist da Frota';

    protected $table = 'frota_modelos_checklist';

    public const TIPO_RAPIDO = 'rapido';

    public const TIPO_COMPLETO = 'completo';

    protected $fillable = ['tenant_id', 'nome', 'tipo', 'tipo_veiculo', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [self::TIPO_RAPIDO => 'Rápido', self::TIPO_COMPLETO => 'Completo'];
    }

    /** @return array<string, string> */
    public static function tipoVeiculoLabels(): array
    {
        return ['leve' => 'Somente veículo leve', 'pesado' => 'Somente veículo pesado'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(FrotaItemModeloChecklist::class, 'modelo_id')->orderBy('ordem');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(FrotaChecklist::class, 'modelo_id');
    }

    /** O modelo vale para este veículo? (tipo_veiculo nulo = todos) */
    public function serveParaVeiculo(Asset $ativo): bool
    {
        return match ($this->tipo_veiculo) {
            'pesado' => (bool) $ativo->veiculo_pesado,
            'leve' => ! $ativo->veiculo_pesado,
            default => true,
        };
    }
}
