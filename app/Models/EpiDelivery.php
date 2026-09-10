<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Concerns\HasSignatures;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ficha de entrega/emprestimo de EPI por colaborador (compliance NR-6).
 * Cada ciclo de entrega/devolucao e' uma linha nova -- nunca reaproveita
 * uma linha existente pra um novo emprestimo, isso e' o que garante
 * rastreabilidade completa em caso de acidente ("quais EPIs este
 * colaborador tinha e desde quando" = so' filtrar por employee_id).
 *
 * blocked/blocked_reason sao preenchidos por uma trigger de banco (nao so'
 * validacao de app) que confere o CA vigente do Material entregue contra
 * epi_specifications -- ver migration create_epi_deliveries_table. O model
 * nunca deve setar blocked manualmente na criacao normal; a trigger decide
 * (em Postgres; em sqlite/testes a trigger nao existe, ver guard na
 * migration, e o bloqueio fica a cargo da validacao do Resource).
 */
class EpiDelivery extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasSignatures;
    use HasUuids;
    use SoftDeletes;

    protected static ?string $saasFeatureKey = 'tabela_epi_deliveries';

    protected static ?string $saasPermissionSlug = 'entrega_epi';

    protected static ?string $saasModuleLabel = 'Entrega de EPI';

    public const REASON_ENTREGA_INICIAL = 'entrega_inicial';

    public const REASON_TROCA_DESGASTE = 'troca_desgaste';

    public const REASON_PERDA = 'perda';

    public const REASON_DANO = 'dano';

    public const REASON_EMPRESTIMO_TEMPORARIO = 'emprestimo_temporario';

    public const REASON_TROCA_CA_VENCIDO = 'troca_ca_vencido';

    public const REASON_AJUSTE = 'ajuste';

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_DEVOLVIDO = 'devolvido';

    public const STATUS_SUBSTITUIDO = 'substituido';

    public const STATUS_EXTRAVIADO = 'extraviado';

    public const RETURNED_CONDITION_BOA = 'boa';

    public const RETURNED_CONDITION_DANIFICADA = 'danificada';

    public const RETURNED_CONDITION_PERDIDA = 'perdida';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'material_id',
        'internal_unit_id',
        'quantity',
        'reason',
        'ownership_mode',
        'status',
        'delivered_at',
        'delivered_by_user_id',
        'expected_return_at',
        'returned_at',
        'returned_by_user_id',
        'returned_condition',
        'replaced_by_delivery_id',
        'material_stock_movement_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'delivered_at' => 'datetime',
        'expected_return_at' => 'date',
        'returned_at' => 'datetime',
        'blocked' => 'boolean',
    ];

    protected $attributes = [
        'status' => self::STATUS_ATIVO,
    ];

    public static function reasonLabels(): array
    {
        return [
            self::REASON_ENTREGA_INICIAL => 'Entrega Inicial',
            self::REASON_TROCA_DESGASTE => 'Troca por Desgaste',
            self::REASON_PERDA => 'Reposição por Perda',
            self::REASON_DANO => 'Reposição por Dano',
            self::REASON_EMPRESTIMO_TEMPORARIO => 'Empréstimo Temporário',
            self::REASON_TROCA_CA_VENCIDO => 'Troca por CA Vencido',
            self::REASON_AJUSTE => 'Ajuste',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ATIVO => 'Ativo (em posse do colaborador)',
            self::STATUS_DEVOLVIDO => 'Devolvido',
            self::STATUS_SUBSTITUIDO => 'Substituído',
            self::STATUS_EXTRAVIADO => 'Extraviado',
        ];
    }

    public static function returnedConditionLabels(): array
    {
        return [
            self::RETURNED_CONDITION_BOA => 'Boa (reutilizável)',
            self::RETURNED_CONDITION_DANIFICADA => 'Danificada',
            self::RETURNED_CONDITION_PERDIDA => 'Perdida',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function replacedByDelivery(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_delivery_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(MaterialStockMovement::class, 'material_stock_movement_id');
    }

    public function isOverdueForReturn(): bool
    {
        return $this->ownership_mode === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO
            && $this->status === self::STATUS_ATIVO
            && $this->returned_at === null
            && $this->expected_return_at !== null
            && $this->expected_return_at->isPast();
    }

    /**
     * Dias em uso desde a entrega -- comparado contra
     * epi_specifications.estimated_lifespan_days pra sugerir troca por
     * fim de vida util (nao bloqueia, so' informa).
     */
    public function daysInUse(): int
    {
        return (int) $this->delivered_at->diffInDays(now());
    }

    public function isLifespanExceeded(): bool
    {
        $lifespan = $this->material->epiSpecification?->estimated_lifespan_days;

        if (! $lifespan || $this->status !== self::STATUS_ATIVO) {
            return false;
        }

        return $this->daysInUse() >= $lifespan;
    }
}
