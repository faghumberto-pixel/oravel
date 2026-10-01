<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parcela da taxa de implantação, com a cobrança avulsa correspondente na Asaas. */
class ImplementationCharge extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const PENDENTE = 'pendente';

    public const PAGO = 'pago';

    public const ATRASADO = 'atrasado';

    public const CANCELADO = 'cancelado';

    protected $fillable = [
        'tenant_id', 'installment_number', 'installments_total', 'amount', 'due_date',
        'asaas_payment_id', 'status', 'invoice_url', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
