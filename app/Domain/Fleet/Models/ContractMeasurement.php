<?php

namespace App\Domain\Fleet\Models;

use App\Models\AccountReceivable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medição mensal consolidada de um contrato: valor base (proporcional via
 * pró-rata quando o contrato começou/terminou no meio do período) +
 * excedente de franquia de horas (link opcional pra
 * RentalOverageCharge/ContractOverageCalculator já existentes -- não
 * duplica o cálculo de horímetro) + extras (mobilização/desmobilização,
 * ver ContractMeasurementExtra) = total_amount.
 *
 * Workflow de status: draft -> awaiting_approval -> approved -> invoiced,
 * ou awaiting_approval -> rejected. approve() só marca "aprovado" (decisão
 * de negócio); a Conta a Receber real só é criada em markInvoiced(), pra
 * separar "financeiro concordou com o valor" de "cobrança de fato emitida"
 * -- mesmo princípio de RentalOverageCharge::approve(), mas em 2 passos
 * em vez de 1 porque o enum pedido tem os 2 estados.
 */
class ContractMeasurement extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_REJECTED = 'rejected';

    protected static ?string $saasFeatureKey = 'tabela_contract_measurements';

    protected static ?string $saasPermissionSlug = 'medicao_contrato';

    protected static ?string $saasModuleLabel = 'Medições de Contrato';

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'reference_period_start',
        'reference_period_end',
        'total_days_in_period',
        'prorated_days',
        'total_base_amount',
        'total_excess_hours_amount',
        'total_extras_amount',
        'total_amount',
        'rental_overage_charge_id',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'account_receivable_id',
    ];

    protected $casts = [
        'reference_period_start' => 'date',
        'reference_period_end' => 'date',
        'total_days_in_period' => 'integer',
        'prorated_days' => 'integer',
        'total_base_amount' => 'decimal:2',
        'total_excess_hours_amount' => 'decimal:2',
        'total_extras_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_AWAITING_APPROVAL => 'Aguardando Aprovação',
            self::STATUS_APPROVED => 'Aprovada',
            self::STATUS_INVOICED => 'Faturada',
            self::STATUS_REJECTED => 'Rejeitada',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function rentalOverageCharge(): BelongsTo
    {
        return $this->belongsTo(RentalOverageCharge::class);
    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(ContractMeasurementExtra::class);
    }

    public function isProrated(): bool
    {
        return $this->prorated_days < $this->total_days_in_period;
    }

    /**
     * Recalcula total_extras_amount a partir da soma real dos itens em
     * `extras` e atualiza total_amount -- chamado depois de adicionar/
     * remover um item extra, pra nunca deixar os totais dessincronizados
     * da soma real das linhas.
     */
    public function recalculateTotals(): void
    {
        $extrasTotal = $this->extras()->sum('amount');

        $this->total_extras_amount = $extrasTotal;
        $this->total_amount = (float) $this->total_base_amount
            + (float) $this->total_excess_hours_amount
            + (float) $extrasTotal;

        $this->save();
    }

    /**
     * draft -> awaiting_approval. Only makes sense once the draft has a
     * real total (guards against submitting an empty/zeroed calculation
     * by mistake).
     */
    public function submit(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException('Só é possível enviar para aprovação uma medição em rascunho.');
        }

        $this->update(['status' => self::STATUS_AWAITING_APPROVAL]);
    }

    /**
     * awaiting_approval -> approved. Não gera a Conta a Receber ainda --
     * ver markInvoiced().
     */
    public function approve(User $user): void
    {
        if ($this->status !== self::STATUS_AWAITING_APPROVAL) {
            throw new \RuntimeException('Só é possível aprovar uma medição aguardando aprovação.');
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);
    }

    /**
     * awaiting_approval -> rejected. Motivo obrigatório -- quem revisa
     * depois precisa saber por que essa medição não virou cobrança.
     */
    public function reject(User $user, string $reason): void
    {
        if ($this->status !== self::STATUS_AWAITING_APPROVAL) {
            throw new \RuntimeException('Só é possível rejeitar uma medição aguardando aprovação.');
        }

        if (trim($reason) === '') {
            throw new \RuntimeException('Informe o motivo da rejeição.');
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * approved -> invoiced. Gera a Conta a Receber consolidada (payload
     * de faturamento) -- mesmo padrão de RentalOverageCharge::approve(),
     * mas aqui cobrindo o valor TOTAL da medição (base + excedente +
     * extras), não só o excedente isolado.
     */
    public function markInvoiced(?\DateTimeInterface $dueDate = null): AccountReceivable
    {
        if ($this->status !== self::STATUS_APPROVED) {
            throw new \RuntimeException('Só é possível faturar uma medição aprovada.');
        }

        if ((float) $this->total_amount <= 0) {
            throw new \RuntimeException('Não há valor a cobrar nesta medição.');
        }

        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant_id,
            'client_id' => $this->contract->client_id,
            'contract_id' => $this->contract_id,
            'description' => sprintf(
                'Medição de contrato #%s — %s a %s',
                $this->contract->contract_number ?? $this->contract_id,
                $this->reference_period_start->format('d/m/Y'),
                $this->reference_period_end->format('d/m/Y')
            ),
            'amount' => $this->total_amount,
            'due_date' => $dueDate ?? now()->addDays(15),
            'mes' => $this->reference_period_start->month,
            'ano' => $this->reference_period_start->year,
        ]);

        $this->update([
            'status' => self::STATUS_INVOICED,
            'account_receivable_id' => $receivable->id,
        ]);

        return $receivable;
    }
}
