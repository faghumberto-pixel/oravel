<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * id (uuid) sem HasUuids ate 2026-07-16 -- nunca pegava porque 0
 * InternalUnit foi criado em produto real ate' agora (Resource so' virou
 * visivel recentemente, ver docblock do InternalUnitResource). Estourava
 * "null value in column id" na primeira tentativa real de criar uma.
 */
class InternalUnit extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_internal_units';

    protected static ?string $saasPermissionSlug = 'unidade_interna';

    protected static ?string $saasModuleLabel = 'Unidades Internas';

    /**
     * cep/address/city/state/latitude/longitude ja existiam na tabela
     * (migrations add_geoloc_fields_to_internal_units_table.php) e ja
     * apareciam no form do InternalUnitResource -- mas como nao estavam
     * aqui, o mass-assignment descartava tudo silenciosamente ao salvar.
     * code/is_active tambem ja existiam na tabela desde a criacao, nunca
     * expostos. responsible_user_id/type sao novos (InternalUnit vira a
     * "filial" do estoque por localizacao).
     */
    protected $fillable = [
        'name', 'tenant_id', 'description',
        'cep', 'address', 'city', 'state', 'latitude', 'longitude',
        'code', 'is_active', 'responsible_user_id', 'type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Injeção automática e segura do tenant_id para isolamento dos dados
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id
                                    ?? filament()->getTenant()?->id
                                    ?? session('tenant_id');
            }
        });
    }

    /**
     * O vínculo que o Filament está cobrando
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function locationStocks(): HasMany
    {
        return $this->hasMany(MaterialLocationStock::class);
    }

    /**
     * Filial padrao pra operacoes de estoque quando nao ha um lugar
     * explicito pra resolver (ex.: OS cujo Ativo nao tem internal_unit_id
     * definido) -- usa a mais antiga cadastrada, ou cria uma "Matriz" se o
     * tenant ainda nao tem nenhuma (mesmo criterio do backfill em
     * 2026_07_16_110500_backfill_material_location_stock.php).
     */
    public static function defaultForTenant(string $tenantId): self
    {
        return static::where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->first() ?? static::create([
                'tenant_id' => $tenantId,
                'name' => 'Matriz',
                'code' => 'MATRIZ',
                'type' => 'matriz',
                'is_active' => true,
            ]);
    }
}
