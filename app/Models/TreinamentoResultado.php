<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um envio do teste público de treinamento. Global de propósito (sem tenant):
 * quem responde não tem conta; a Oravel acompanha pela Central.
 */
class TreinamentoResultado extends Model
{
    use HasUuids;

    protected $table = 'treinamento_resultados';

    protected $fillable = [
        'treinamento',
        'nome',
        'funcao',
        'empresa',
        'tenant_id',
        'acertos',
        'total',
        'nota',
        'aprovado',
        'respostas',
        'ip',
        'user_agent',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withoutGlobalScopes();
    }

    protected $casts = [
        'nota' => 'decimal:1',
        'aprovado' => 'boolean',
        'respostas' => 'array',
    ];
}
