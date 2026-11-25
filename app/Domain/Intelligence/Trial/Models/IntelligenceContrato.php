<?php

namespace App\Domain\Intelligence\Trial\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aceite online do contrato do plano básico: texto exato, hash, quem aceitou, quando e de onde. */
class IntelligenceContrato extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'intelligence_contratos';

    protected $fillable = [
        'trial_id', 'tenant_id', 'versao', 'hash', 'texto', 'razao_social', 'cnpj', 'email', 'responsavel', 'forma_pagamento',
        'preco', 'billing_starts_on', 'aceite_em', 'ip', 'user_agent', 'pdf_path',
    ];

    protected $casts = ['preco' => 'decimal:2', 'billing_starts_on' => 'date', 'aceite_em' => 'datetime'];

    public function trial(): BelongsTo
    {
        return $this->belongsTo(IntelligenceTrial::class, 'trial_id')->withoutGlobalScopes();
    }
}
