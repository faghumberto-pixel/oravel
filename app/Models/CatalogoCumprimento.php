<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evidencia de que um ativo cumpriu um requisito (documento/inspecao/checklist/acessorio) ou uma preventiva do catalogo.
 * E a fonte unica que o AssetFitnessService le (alem da NR-13 e dos medidores do proprio Asset).
 *
 * HISTORICO IMUTAVEL: o snapshot guarda a regra exigida NA EPOCA (nome, efeito, validade, intervalo...) e nunca muda;
 * valido_ate e calculado uma vez, na criacao. Editar o catalogo depois nao altera registros antigos.
 */
class CatalogoCumprimento extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const OK = 'ok';

    public const REPROVADO = 'reprovado';

    protected $table = 'catalogo_cumprimentos';

    protected $guarded = [];

    protected $casts = [
        'realizado_em' => 'datetime',
        'valido_ate' => 'date',
        'medidor_leitura' => 'decimal:2',
        'snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if ($c->snapshot) {
                return;
            }

            $c->realizado_em ??= now();

            if ($c->requisito_id && ($r = CatalogoRequisito::withoutGlobalScopes()->with(['item' => fn ($q) => $q->withoutGlobalScopes()])->find($c->requisito_id))) {
                $c->snapshot = [
                    'tipo' => 'requisito', 'slug' => $r->slug, 'nome' => $r->item?->nome ?? $r->slug, 'efeito' => $r->efeito,
                    'validade_dias' => $r->validade_dias, 'gera_os' => (bool) $r->gera_os, 'norma' => $r->norma,
                ];
                if (! $c->valido_ate && $r->validade_dias && $c->resultado === self::OK) {
                    $c->valido_ate = Carbon::parse($c->realizado_em)->addDays($r->validade_dias)->toDateString();
                }
            } elseif ($c->preventiva_id && ($p = CatalogoPreventiva::withoutGlobalScopes()->find($c->preventiva_id))) {
                $c->snapshot = [
                    'tipo' => 'preventiva', 'slug' => $p->slug, 'nome' => $p->nome, 'medidor' => $p->medidor, 'intervalo' => $p->intervalo,
                    'tolerancia_pct' => $p->tolerancia_pct, 'ou_dias' => $p->ou_dias,
                ];
            }
        });

        // snapshot e write-once
        static::updating(function (self $c) {
            if ($c->isDirty('snapshot')) {
                $c->snapshot = $c->getOriginal('snapshot');
            }
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
