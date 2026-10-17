<?php

namespace App\Models;

use App\Http\Controllers\PlanosPublicosController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo global de taxas de configuração (pagamento único, opcionais). Valores editados na Central.
 * Fora do escopo de tenant de propósito: é preço de tabela da Oravel, não dado de cliente.
 */
class TaxaConfiguracao extends Model
{
    use HasUuids;

    protected $table = 'taxas_configuracao';

    protected $fillable = ['codigo', 'nome', 'descricao', 'valor', 'ativa', 'ordem', 'complemento_feature'];

    protected $casts = ['valor' => 'decimal:2', 'ativa' => 'boolean', 'ordem' => 'integer'];

    protected static function booted(): void
    {
        // A API pública de preços (site) lê o catálogo: qualquer mudança limpa o cache.
        static::saved(fn () => Cache::forget(PlanosPublicosController::CACHE_KEY));
        static::deleted(fn () => Cache::forget(PlanosPublicosController::CACHE_KEY));
    }

    /** Taxas que o cliente pode aprovar: ativas e com valor definido. */
    public function scopeDisponiveis(Builder $query): Builder
    {
        return $query->where('ativa', true)->where('valor', '>', 0)->orderBy('ordem')->orderBy('nome');
    }
}
