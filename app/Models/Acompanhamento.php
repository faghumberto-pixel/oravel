<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acompanhamento do painel Central (remodelagem do site, uma pesquisa etc.): cada um tem previa opcional,
 * lista de itens, notas e pendencias. Tabelas GLOBAIS (sem tenant_id): controle interno da Oravel.
 */
class Acompanhamento extends Model
{
    use HasUuids;

    public const TIPOS = ['projeto' => 'Projeto', 'pesquisa' => 'Pesquisa'];

    public const STATUS = ['ativo' => 'Ativo', 'concluido' => 'Concluído', 'arquivado' => 'Arquivado'];

    protected $table = 'acompanhamentos';

    protected $guarded = [];

    public function notas(): HasMany
    {
        return $this->hasMany(AcompanhamentoNota::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(AcompanhamentoItem::class);
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(AcompanhamentoPendencia::class);
    }
}
