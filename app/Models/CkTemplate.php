<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Checklist por grupo de equipamento (modelo editavel do tenant). O YAML em database/seeders/checklists e so o ponto de
 * partida; a fonte da verdade e o banco. Raiz SaaS do modulo: a chave fica DESLIGADA por padrao nos contratos
 * (saas:sync-modules grava false para chaves novas). Tabelas ck_*: o nome checklist_templates e do modulo antigo.
 */
class CkTemplate extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    public const FEATURE_KEY = 'tabela_ck_templates';

    public const ORIGEM_PADRAO = 'padrao';

    public const ORIGEM_CUSTOMIZADO = 'customizado';

    public const TIPOS = ['pre_uso' => 'Pré-uso', 'diario' => 'Diário', 'periodico' => 'Periódico', 'saida' => 'Saída', 'entrada' => 'Entrada'];

    public const PERIODICIDADES = ['por_uso' => 'Por uso', 'diaria' => 'Diária', 'semanal' => 'Semanal', 'mensal' => 'Mensal', 'por_medidor' => 'Por horímetro/km'];

    protected static ?string $saasFeatureKey = self::FEATURE_KEY;

    protected static ?string $saasPermissionSlug = 'checklist_equipamento';

    protected static ?string $saasModuleLabel = 'Checklists de Equipamento';

    protected $table = 'ck_templates';

    protected $guarded = [];

    protected $casts = ['ativo' => 'boolean', 'personalizado' => 'boolean', 'versao' => 'integer', 'versao_template' => 'integer', 'intervalo_horimetro_km' => 'integer'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'grupo_id');
    }

    public function secoes(): HasMany
    {
        return $this->hasMany(CkSection::class, 'template_id')->orderBy('ordem');
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(CkExecution::class, 'template_id');
    }
}
