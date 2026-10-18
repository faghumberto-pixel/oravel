<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Onboarding guiado (ver config/onboarding.php). Isolado por empresa (BelongsToTenant). */
class OnboardingProgresso extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'onboarding_progresso';

    protected $fillable = ['tenant_id', 'solution', 'step_key', 'completed_at', 'skipped_at'];

    protected $casts = [
        'completed_at' => 'datetime', 'skipped_at' => 'datetime', 'iniciado_em' => 'datetime', 'primeiro_acesso_em' => 'datetime',
        'concluido_em' => 'datetime', 'dispensado_em' => 'datetime', 'enviado_em' => 'datetime',
    ];
}
