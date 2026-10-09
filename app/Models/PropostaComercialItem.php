<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de linha da proposta -- equipamento (asset_category_id; o
 * patrimônio/Asset exato só é escolhido depois, na SolicitacaoLocacao,
 * igual já acontece hoje) ou serviço (texto livre em description, sem
 * catálogo -- mesmo padrão de QuoteItem::TYPE_SERVICO). Prazo
 * (start_date/end_date) e exigência (item_terms) são próprios de CADA
 * item, distintos de valid_until/terms da proposta como um todo.
 */
class PropostaComercialItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $table = 'proposta_comercial_itens';

    public const TYPE_EQUIPAMENTO = 'equipamento';

    public const TYPE_SERVICO = 'servico';

    public const TYPE_MAO_DE_OBRA = 'mao_de_obra';

    public const TYPE_SEGURANCA_DOCUMENTACAO = 'seguranca_documentacao';

    public const TYPE_ACESSORIO = 'acessorio';

    public const TYPE_INSUMO = 'insumo';

    protected $fillable = [
        'tenant_id',
        'proposta_comercial_id',
        'asset_category_id',
        'aggregate_item_type_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'unit_period',
        'start_date',
        'end_date',
        'item_terms',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_EQUIPAMENTO => 'Locação de equipamento',
            self::TYPE_MAO_DE_OBRA => 'Mão de obra especializada',
            self::TYPE_SEGURANCA_DOCUMENTACAO => 'Segurança e documentação',
            self::TYPE_ACESSORIO => 'Acessórios e componentes',
            self::TYPE_INSUMO => 'Insumos e consumíveis',
            self::TYPE_SERVICO => 'Serviço (outros)',
        ];
    }

    /**
     * Categorias vendidas separadas da locação: cada uma vira um contrato
     * próprio quando o cliente aceita a proposta.
     *
     * @return array<int, string>
     */
    public static function serviceCategories(): array
    {
        return [
            self::TYPE_MAO_DE_OBRA,
            self::TYPE_SEGURANCA_DOCUMENTACAO,
            self::TYPE_ACESSORIO,
            self::TYPE_INSUMO,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function unitPeriodLabels(): array
    {
        return [
            'avulso' => 'Avulso',
            'hora' => 'Por Hora',
            'diaria' => 'Diária',
            'semanal' => 'Semanal',
            'mensal' => 'Mensal',
        ];
    }

    protected static function booted(): void
    {
        // subtotal sempre recalculado a partir de quantity/unit_price --
        // mesmo padrão de QuoteItem, nunca confia no valor vindo do form.
        static::saving(function (PropostaComercialItem $item) {
            $item->subtotal = round($item->quantity * $item->unit_price, 2);
        });
    }

    public function aggregateItemType(): BelongsTo
    {
        return $this->belongsTo(AggregateItemType::class);
    }

    public function propostaComercial(): BelongsTo
    {
        return $this->belongsTo(PropostaComercial::class);
    }

    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }
}
