<?php

namespace App\Domain\Intelligence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Depoimento real da página do teste grátis. Global (sem tenant): é conteúdo do site da Oravel. */
class IntelligenceTestimonial extends Model
{
    use HasUuids;

    protected $table = 'intelligence_testimonials';

    protected $fillable = ['nome', 'cargo', 'empresa', 'texto', 'autorizacao_uso', 'autorizacao_registro', 'aprovado', 'ordem'];

    protected $casts = ['autorizacao_uso' => 'boolean', 'aprovado' => 'boolean', 'ordem' => 'integer'];

    /** O que a página pode mostrar: aprovado E com autorização de uso. */
    public function scopePublicaveis(Builder $q): Builder
    {
        return $q->where('aprovado', true)->where('autorizacao_uso', true)->orderBy('ordem')->orderBy('created_at');
    }
}
