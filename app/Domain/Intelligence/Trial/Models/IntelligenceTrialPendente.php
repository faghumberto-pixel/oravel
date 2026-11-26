<?php

namespace App\Domain\Intelligence\Trial\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Cadastro à espera da confirmação do e-mail. Não é tenant-scoped de propósito: ainda não existe tenant. */
class IntelligenceTrialPendente extends Model
{
    use HasUuids;

    protected $table = 'intelligence_trial_pendentes';

    protected $fillable = ['nome', 'empresa', 'whatsapp', 'email', 'senha_hash', 'token_hash', 'ip', 'user_agent', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    protected $hidden = ['senha_hash', 'token_hash'];

    public static function porToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
    }
}
