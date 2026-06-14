<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\BelongsToTenant;

class AccountPayable extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_account_payables";
    protected static ?string $saasPermissionSlug = "contas_pagar";
    protected static ?string $saasModuleLabel = "Contas a Pagar";

    use BelongsToTenant, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'description', 'amount', 'due_date', 'payment_date', 
        'status', 'tenant_id', 'bill_category_id', 
        'branch_id', 'cost_center_id', 'asset_id', 
        'mes', 'ano'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($account) {
            if ($account->due_date) {
                $ref = $account->payment_date ? \Carbon\Carbon::parse($account->payment_date) : \Carbon\Carbon::parse($account->due_date);
                $account->mes = $ref->format('m');
                $account->ano = $ref->format('Y');
            }
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function billCategory(): BelongsTo { return $this->belongsTo(BillCategory::class, 'bill_category_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function costCenter(): BelongsTo { return $this->belongsTo(CostCenter::class, 'cost_center_id'); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class, 'asset_id'); }
}