<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReceivable extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_account_receivables';

    protected static ?string $saasPermissionSlug = 'contas_receber';

    protected static ?string $saasModuleLabel = 'Contas a Receber';

    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'description', 'amount', 'due_date', 'payment_date',
        'status', 'tenant_id', 'bill_category_id',
        'branch_id', 'cost_center_id', 'client_id', 'contract_id',
        'billing_plan_id', 'quote_id', 'multa_percentual', 'multa_valor',
        'mes', 'ano',
        'asaas_payment_id', 'asaas_invoice_url', 'asaas_boleto_url',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
        'multa_percentual' => 'decimal:2',
        'multa_valor' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function ($account) {
            if ($account->due_date) {
                $ref = $account->payment_date ? Carbon::parse($account->payment_date) : Carbon::parse($account->due_date);
                $account->mes = $ref->format('m');
                $account->ano = $ref->format('Y');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function billCategory(): BelongsTo
    {
        return $this->belongsTo(BillCategory::class, 'bill_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }

    /**
     * Origem: App\Models\Quote::forwardToFinanceiro() (POP 4 da auditoria)
     * -- nulo pra contas a receber lançadas manualmente, sem origem num
     * orçamento.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Multa sugerida = Contract.multa_rescisoria (percentual ja existente no
     * contrato) sobre o valor da conta -- so' aplicada quando a automacao de
     * multas (enabled_modules['contas_a_receber']) esta ligada, ver
     * AccountReceivableResource "Dar Baixa".
     */
    public function calculateLateFee(): ?float
    {
        $pct = $this->multa_percentual ?? $this->contract?->multa_rescisoria;

        return $pct ? round((float) $this->amount * ((float) $pct / 100), 2) : null;
    }
}
