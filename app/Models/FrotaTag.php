<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tag de pedágio (Sem Parar, ConectCar...) de um veículo. */
class FrotaTag extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_tags';

    protected static ?string $saasPermissionSlug = 'tag_frota';

    protected static ?string $saasModuleLabel = 'Tags de Pedágio da Frota';

    protected $table = 'frota_tags';

    protected $attributes = ['ativa' => true];

    protected $fillable = ['tenant_id', 'ativo_id', 'numero', 'operadora', 'observacoes', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    /** @return array<string, string> */
    public static function operadoraLabels(): array
    {
        return ['sem_parar' => 'Sem Parar', 'conectcar' => 'ConectCar', 'veloe' => 'Veloe', 'move_mais' => 'Move Mais', 'outra' => 'Outra'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
