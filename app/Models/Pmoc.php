<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pmoc extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'titulo',
        'descricao',
        'local_ambiente',
        'tipo_sistema',
        'asset_id',
        'responsavel_nome',
        'responsavel_telefone',
        'responsavel_email',
        'data_inicio_vigencia',
        'data_fim_vigencia',
        'frequencia_dias',
        'procedimentos_limpeza',
        'procedimentos_filtros',
        'procedimentos_inspecao',
        'procedimentos_medicao',
        'temperatura_ideal_min',
        'temperatura_ideal_max',
        'umidade_ideal_min',
        'umidade_ideal_max',
        'parametros_qualidade_ar',
        'observacoes',
        'status',
    ];

    protected $casts = [
        'data_inicio_vigencia' => 'date',
        'data_fim_vigencia' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(PmocCheck::class);
    }
}
