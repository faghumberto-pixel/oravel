<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de linha do orçamento (peça ou serviço) -- mesmo formato de
 * MaintenanceOrderMaterial (quantity + unit_price), material_id opcional
 * quando o item vem de um material real do Almoxarifado.
 */
class QuoteItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    public const TYPE_PECA = 'peca';

    public const TYPE_SERVICO = 'servico';

    protected $fillable = [
        'tenant_id',
        'quote_id',
        'material_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_PECA => 'Peça',
            self::TYPE_SERVICO => 'Serviço',
        ];
    }

    protected static function booted(): void
    {
        // subtotal sempre recalculado a partir de quantity/unit_price --
        // nunca confia em subtotal vindo direto do form, evita ficar
        // dessincronizado se o form so' mandar 2 dos 3 campos.
        static::saving(function (QuoteItem $item) {
            $item->subtotal = round($item->quantity * $item->unit_price, 2);
        });
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
