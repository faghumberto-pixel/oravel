<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    /** Grupo de checklist (checklist_groups): o que ativa o checklist na OS dos ativos desse grupo. */
    public function grupoChecklist(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class, 'checklist_group_id');
    }

    public function secoes(): HasMany
    {
        return $this->hasMany(CkSection::class, 'template_id')->orderBy('ordem');
    }

    public function itens(): HasManyThrough
    {
        return $this->hasManyThrough(CkItem::class, CkSection::class, 'template_id', 'section_id');
    }

    /**
     * Impressao digital do que o tenant edita (campos, secoes e itens ativos e a SEQUENCIA deles), lida do banco na hora. Usa a
     * posicao e nao o numero da coluna ordem: o repeater do Filament regrava ordem a partir de 1 e isso nao e edicao.
     * Serve para saber se uma edicao mudou o conteudo (e entao subir a versao) sem depender do que o formulario reporta.
     */
    public function hashConteudo(): string
    {
        $campos = self::withoutGlobalScopes()->withTrashed()->whereKey($this->getKey())
            ->first(['nome', 'grupo_id', 'checklist_group_id', 'tipo', 'periodicidade', 'intervalo_horimetro_km', 'referencia_manual', 'observacoes', 'ativo'])?->toArray();

        $secoes = CkSection::withoutGlobalScopes()->where('template_id', $this->getKey())->whereNull('deleted_at')
            ->orderBy('ordem')->orderBy('id')->get(['id', 'titulo']);

        $itens = CkItem::withoutGlobalScopes()->whereIn('section_id', $secoes->pluck('id'))->whereNull('deleted_at')
            ->orderBy('section_id')->orderBy('ordem')->orderBy('id')
            ->get(['id', 'section_id', 'descricao', 'tipo_resposta', 'unidade', 'opcoes', 'obrigatorio', 'critico', 'exige_foto_se_nao_conforme', 'referencia_manual', 'validar_norma', 'catalogo_requisito_id', 'ativo']);

        return md5(json_encode([$campos, $secoes->toArray(), $itens->toArray()]));
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(CkExecution::class, 'template_id');
    }
}
