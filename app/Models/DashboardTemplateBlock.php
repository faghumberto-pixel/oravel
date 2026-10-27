<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um bloco dentro de um modelo de dashboard. Sem tenant_id próprio: o acesso é sempre pelo modelo (pai),
 * que carrega o escopo. `config` ajusta o bloco; a ficha é só da Central (não aparece para a empresa).
 */
class DashboardTemplateBlock extends Model
{
    use HasUuids;

    protected $fillable = ['dashboard_template_id', 'block_key', 'position', 'config', 'ficha_dor', 'ficha_indicador', 'ficha_meta', 'ficha_revisao_em'];

    protected $casts = ['config' => 'array', 'position' => 'integer', 'ficha_revisao_em' => 'date'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DashboardTemplate::class, 'dashboard_template_id');
    }
}
