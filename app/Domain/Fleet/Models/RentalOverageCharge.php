<?php

namespace App\Domain\Fleet\Models;

use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalOverageCharge extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Cálculo automático não confiou no resultado (contratos sobrepostos no
     * mesmo Asset, sem constraint que impeça isso -- ou leituras de
     * horímetro insuficientes no período) -- pedido do usuário 2026-08-24:
     * nesse caso não aprova sozinho, só alerta o financeiro pra revisar
     * manualmente (ver conflict_reason).
     */
    public const STATUS_CONFLICT = 'conflict';

    protected static ?string $saasFeatureKey = 'tabela_rental_overage_charges';

    protected static ?string $saasPermissionSlug = 'excedente_locacao';

    protected static ?string $saasModuleLabel = 'Excedentes de Locação';

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'asset_id',
        'period_start',
        'period_end',
        'hours_used',
        'hours_included',
        'hours_overage',
        'amount',
        'account_receivable_id',
        'status',
        'conflict_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'hours_used' => 'decimal:2',
        'hours_included' => 'decimal:2',
        'hours_overage' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_INVOICED => 'Faturado',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_CONFLICT => 'Conflito — Revisar Manualmente',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }

    /**
     * Aprova o excedente calculado e gera a conta a receber correspondente
     * -- pedido do usuário 2026-08-24. Mesmo padrão de
     * Quote::forwardToFinanceiro() (guarda de idempotência via status,
     * create() direto do fillable de AccountReceivable).
     */
    public function approve(User $user, ?\DateTimeInterface $dueDate = null): AccountReceivable
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Só é possível aprovar um excedente pendente.');
        }

        if ((float) $this->amount <= 0) {
            throw new \RuntimeException('Não há valor a cobrar neste excedente.');
        }

        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant_id,
            'client_id' => $this->contract->client_id,
            'contract_id' => $this->contract_id,
            'description' => sprintf(
                'Excedente de franquia de horas — Contrato #%s — %s a %s (%.2fh acima da franquia)',
                $this->contract->contract_number ?? $this->contract_id,
                $this->period_start->format('d/m/Y'),
                $this->period_end->format('d/m/Y'),
                $this->hours_overage
            ),
            'amount' => $this->amount,
            'due_date' => $dueDate ?? now()->addDays(15),
        ]);

        $this->update([
            'status' => self::STATUS_INVOICED,
            'account_receivable_id' => $receivable->id,
        ]);

        return $receivable;
    }
}
