<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ContractStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.contract-status-widget';

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;
        $signature = $tenant?->signature;
        $requiredBy = $tenant?->signature_required_by;
        $isOverdue = $requiredBy && now()->isAfter($requiredBy) && !$signature;
        $hidePrompt = $tenant?->hide_signature_prompt;

        // Se tenant escolheu "não mostrar novamente" e NÃO está vencido, retorna null pra esconder widget
        if ($hidePrompt && !$isOverdue && !$signature) {
            return ['hidden' => true];
        }

        return [
            'hidden' => false,
            'hasSignature' => $signature !== null,
            'signedAt' => $signature?->signed_at,
            'signerName' => $signature?->name,
            'signerEmail' => $signature?->email,
            'requiredBy' => $requiredBy,
            'isOverdue' => $isOverdue,
            'daysLeft' => $requiredBy ? now()->diffInDays($requiredBy, false) : null,
        ];
    }
}
