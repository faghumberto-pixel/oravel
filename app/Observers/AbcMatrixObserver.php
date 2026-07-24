<?php

namespace App\Observers;

use App\Models\AbcMatrix;
use App\Models\AbcMatrixHistory;

/**
 * Grava em AbcMatrixHistory toda vez que o nível de um Ativo é definido
 * (created) ou muda (updated com 'nivel' dirty) -- AbcMatrix::updateOrCreate()
 * é chamado em pelo menos 2 lugares (AbcMatrixResource e o modal de dentro
 * de MaintenanceOrderResource), então o histórico precisa nascer aqui, não
 * em cada call site.
 */
class AbcMatrixObserver
{
    public function created(AbcMatrix $matrix): void
    {
        AbcMatrixHistory::create([
            'tenant_id' => $matrix->tenant_id,
            'asset_id' => $matrix->asset_id,
            'nivel_anterior' => null,
            'nivel_novo' => $matrix->nivel,
            'changed_by_user_id' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    public function updated(AbcMatrix $matrix): void
    {
        if (! $matrix->isDirty('nivel')) {
            return;
        }

        AbcMatrixHistory::create([
            'tenant_id' => $matrix->tenant_id,
            'asset_id' => $matrix->asset_id,
            'nivel_anterior' => $matrix->getOriginal('nivel'),
            'nivel_novo' => $matrix->nivel,
            'changed_by_user_id' => auth()->id(),
            'changed_at' => now(),
        ]);
    }
}
