<?php

namespace App\Domain\Intelligence\Concerns;

use LogicException;

/** Registro-fotografia: depois de criado nunca é alterado nem apagado pelo app (o histórico da empresa não se reescreve). */
trait Imutavel
{
    // Só existe created_at (a coluna tem default no banco); não há updated_at porque o registro nunca muda.
    public function usesTimestamps(): bool
    {
        return false;
    }

    protected static function bootImutavel(): void
    {
        static::creating(fn ($m) => $m->created_at ??= now());
        static::updating(fn () => throw new LogicException(static::class.' é imutável: gere um novo snapshot em vez de alterar.'));
        static::deleting(fn () => throw new LogicException(static::class.' é imutável e não pode ser apagado.'));
    }
}
