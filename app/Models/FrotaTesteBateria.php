<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Teste de tensão. Sem tenant_id (isolamento pela bateria/veículo). bateria_id nulo = medida no checklist sem saber qual bateria. */
class FrotaTesteBateria extends Model
{
    use HasUuids;

    protected $table = 'frota_testes_bateria';

    protected $fillable = ['bateria_id', 'ativo_id', 'checklist_id', 'tensao', 'odometro', 'testado_em'];

    protected $casts = ['tensao' => 'decimal:2', 'testado_em' => 'datetime', 'odometro' => 'integer'];

    public function bateria(): BelongsTo
    {
        return $this->belongsTo(FrotaBateria::class, 'bateria_id');
    }

    public function ativo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
