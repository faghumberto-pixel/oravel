<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    use HasSaaSMetadata, BelongsToTenant;

    protected static ?string $saasFeatureKey = 'tabela_bank_reconciliation';

    protected static ?string $saasPermissionSlug = 'reconciliacao_bancaria';

    protected static ?string $saasModuleLabel = 'Reconciliação Bancária';

    protected $fillable = [];
}
