<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
        'acertos',
        'total',
        'nota',
        'aprovado',
        'respostas',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'nota' => 'decimal:1',
        'aprovado' => 'boolean',
        'respostas' => 'array',
    ];
}
