<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de portaria -- QUALQUER veiculo entrando OU saindo da unidade
 * (visita, fornecedor, entrega de pecas, mobilizacao/desmobilizacao,
 * transferencia, saida externa). Nao confundir com EquipmentPatioArrival
 * (laudo de recebimento fisico de uma EquipmentMovement ja concluida --
 * fluxo totalmente diferente, ver App\Filament\Pages\PatioChegadas).
 */
class PatioEntry extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const DIRECTION_ENTRADA = 'entrada';

    public const DIRECTION_SAIDA = 'saida';

    public const REASON_FORNECEDOR = 'fornecedor';

    public const REASON_ENTREGA_PECAS = 'entrega_pecas';

    public const REASON_DESMOBILIZACAO = 'desmobilizacao';

    public const REASON_MOBILIZACAO = 'mobilizacao';

    public const REASON_VISITA = 'visita';

    public const REASON_SAIDA_EXTERNA = 'saida_externa';

    public const REASON_TRANSFERENCIA = 'transferencia';

    public const REASON_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'direction',
        'plate',
        'is_company_vehicle',
        'driver_name',
        'driver_document',
        'origin',
        'reason',
        'reason_detail',
        'brings_equipment',
        'equipment_is_company',
        'asset_id',
        'arrived_at',
        'registered_by_user_id',
    ];

    protected $casts = [
        'is_company_vehicle' => 'boolean',
        'brings_equipment' => 'boolean',
        'equipment_is_company' => 'boolean',
        'arrived_at' => 'datetime',
    ];

    public static function directionLabels(): array
    {
        return [
            self::DIRECTION_ENTRADA => 'Entrada',
            self::DIRECTION_SAIDA => 'Saída',
        ];
    }

    public static function reasonLabels(): array
    {
        return [
            self::REASON_FORNECEDOR => 'Fornecedor',
            self::REASON_ENTREGA_PECAS => 'Entrega de Peças',
            self::REASON_DESMOBILIZACAO => 'Desmobilização',
            self::REASON_MOBILIZACAO => 'Mobilização',
            self::REASON_VISITA => 'Visita',
            self::REASON_SAIDA_EXTERNA => 'Saída Externa',
            self::REASON_TRANSFERENCIA => 'Transferência entre Unidades',
            self::REASON_OUTRO => 'Outro',
        ];
    }

    /**
     * Opcoes de motivo mostradas no form conforme a direcao -- mobilizacao
     * (equipamento saindo pro cliente) e desmobilizacao (equipamento
     * voltando do cliente) fazem sentido nas duas pontas dependendo do
     * fluxo, entao ficam disponiveis em ambas.
     */
    public static function reasonLabelsForDirection(string $direction): array
    {
        $all = self::reasonLabels();

        if ($direction === self::DIRECTION_SAIDA) {
            return array_intersect_key($all, array_flip([
                self::REASON_MOBILIZACAO,
                self::REASON_DESMOBILIZACAO,
                self::REASON_SAIDA_EXTERNA,
                self::REASON_TRANSFERENCIA,
                self::REASON_OUTRO,
            ]));
        }

        return array_intersect_key($all, array_flip([
            self::REASON_FORNECEDOR,
            self::REASON_ENTREGA_PECAS,
            self::REASON_DESMOBILIZACAO,
            self::REASON_MOBILIZACAO,
            self::REASON_VISITA,
            self::REASON_OUTRO,
        ]));
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
