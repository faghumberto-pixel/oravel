<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;

class CashflowReport extends Model
{
    use HasSaaSMetadata, BelongsToTenant;

    protected static ?string $saasFeatureKey = 'tabela_cashflow_report';

    protected static ?string $saasPermissionSlug = 'cashflow_report';

    protected static ?string $saasModuleLabel = 'Painel Fluxo de Caixa';

    protected $fillable = [];
}
