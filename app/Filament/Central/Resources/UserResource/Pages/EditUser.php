<?php

namespace App\Filament\Central\Resources\UserResource\Pages;

use App\Filament\Central\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Excecao ao 2FA obrigatorio: fora do $fillable de proposito, so grava aqui
     * e so quando quem edita e super admin.
     */
    protected function afterSave(): void
    {
        $actor = auth()->user();

        if (! $actor || ! $actor->isSuperAdmin()) {
            return;
        }

        $exempt = (bool) ($this->data['two_factor_exempt'] ?? false);
        $reason = $exempt ? trim((string) ($this->data['two_factor_exempt_reason'] ?? '')) : null;

        if ($exempt === (bool) $this->record->two_factor_exempt
            && ($reason ?: null) === ($this->record->two_factor_exempt_reason ?: null)) {
            return;
        }

        $this->record->forceFill([
            'two_factor_exempt' => $exempt,
            'two_factor_exempt_reason' => $reason ?: null,
        ])->save();

        Log::warning('Exceção de 2FA alterada na Central', [
            'target_user_id' => $this->record->id,
            'target_email' => $this->record->email,
            'exempt' => $exempt,
            'reason' => $reason,
            'changed_by' => $actor->email,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
