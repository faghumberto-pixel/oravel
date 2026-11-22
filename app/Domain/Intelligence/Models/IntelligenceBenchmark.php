<?php

namespace App\Domain\Intelligence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Agregado ANÔNIMO entre empresas. Sem tenant_id e sem BelongsToTenant de propósito: nunca guarda nem expõe quem contribuiu.
 * Só se grava grupo com amostra >= config('intelligence.benchmark_minimo_empresas'). Acesso pelo módulo "Benchmark".
 */
class IntelligenceBenchmark extends Model
{
    use HasUuids;

    protected $table = 'intelligence_benchmarks';

    protected $fillable = ['indicador', 'regiao', 'porte', 'segmento', 'categoria', 'periodo', 'amostra', 'media', 'p25', 'mediana', 'p75'];

    protected $casts = ['periodo' => 'date', 'amostra' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (self $b) {
            if ($b->amostra < (int) config('intelligence.benchmark_minimo_empresas', 5)) {
                throw new \DomainException('Benchmark com amostra abaixo do mínimo identificaria empresas individuais.');
            }
        });
    }
}
