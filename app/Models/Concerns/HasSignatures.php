<?php

namespace App\Models\Concerns;

use App\Models\DocumentSignature;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSignatures
{
    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }

    /**
     * Retorna assinaturas pendentes.
     */
    public function pendingSignatures(): MorphMany
    {
        return $this->signatures()->pending();
    }

    /**
     * Retorna assinaturas já realizadas.
     */
    public function signedSignatures(): MorphMany
    {
        return $this->signatures()->signed();
    }

    /**
     * Verifica se todas as assinaturas foram completadas.
     */
    public function allSignaturesComplete(): bool
    {
        return $this->signatures()->where('status', '!=', 'signed')->doesntExist();
    }

    /**
     * Conta quantas assinaturas pendentes existem.
     */
    public function countPendingSignatures(): int
    {
        return $this->signatures()->pending()->notExpired()->count();
    }
}
