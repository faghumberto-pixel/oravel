<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmocCheck extends Model
{
    protected $fillable = [
        'pmoc_id',
        'maintenance_order_id',
        'data_verificacao',
        'hora_verificacao',
        'responsavel_verificacao',
        'temperatura_medida',
        'umidade_medida',
        'filtros_verificados',
        'filtros_trocados',
        'limpeza_realizada',
        'limpeza_ok',
        'inspecao_resultado',
        'inspecao_ok',
        'anomalias_detectadas',
        'manutencoes_recomendadas',
        'observacoes',
        'status',
    ];

    protected $casts = [
        'data_verificacao' => 'date',
        'filtros_trocados' => 'boolean',
        'limpeza_ok' => 'boolean',
        'inspecao_ok' => 'boolean',
    ];

    public function pmoc(): BelongsTo
    {
        return $this->belongsTo(Pmoc::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }
}
