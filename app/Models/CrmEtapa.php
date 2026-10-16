<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Etapa de um funil (tabela crm_etapas). `tipo` diz se é etapa aberta ou final (ganho/perdido). */
class CrmEtapa extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const TIPO_ABERTA = 'aberta';

    public const TIPO_GANHO = 'ganho';

    public const TIPO_PERDIDO = 'perdido';

    protected $table = 'crm_etapas';

    protected static ?string $saasFeatureKey = 'tabela_crm_etapas';

    protected static ?string $saasPermissionSlug = 'crm_etapa';

    protected static ?string $saasModuleLabel = 'CRM - Etapas do Funil';

    protected $fillable = ['tenant_id', 'crm_funil_id', 'nome', 'slug', 'ordem', 'probabilidade', 'tipo', 'cor'];

    protected $casts = ['ordem' => 'integer', 'probabilidade' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (CrmEtapa $etapa) {
            if (blank($etapa->slug)) {
                $etapa->slug = Str::slug($etapa->nome, '_');
            }
            if ($etapa->tipo === self::TIPO_GANHO) {
                $etapa->probabilidade = 100;
            } elseif ($etapa->tipo === self::TIPO_PERDIDO) {
                $etapa->probabilidade = 0;
            }
        });
    }

    public static function tipoLabels(): array
    {
        return [
            self::TIPO_ABERTA => 'Em andamento',
            self::TIPO_GANHO => 'Final — Ganho',
            self::TIPO_PERDIDO => 'Final — Perdido',
        ];
    }

    public function funil(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'crm_funil_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'etapa_id');
    }

    public function isAberta(): bool
    {
        return $this->tipo === self::TIPO_ABERTA;
    }

    public function isGanho(): bool
    {
        return $this->tipo === self::TIPO_GANHO;
    }

    public function isPerdido(): bool
    {
        return $this->tipo === self::TIPO_PERDIDO;
    }
}
