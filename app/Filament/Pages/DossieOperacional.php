<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

#[BelongsToFeature('maintenance')]
class DossieOperacional extends Page
{
    protected static ?string $slug = 'maintenance-orders/{record}/dossie';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.dossie-operacional';

    public MaintenanceOrder $record;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function mount(MaintenanceOrder $record): void
    {
        $user = auth()->user();
        abort_unless($user && $user->can('view', $record), 403);

        $this->record = $record->load(['asset', 'client', 'technician', 'evidences', 'equipmentMovements.locations']);
    }

    public function getTitle(): string
    {
        return 'Dossiê Operacional — OS #'.$this->record->os_number;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function isOperationCheckout(): bool
    {
        return $this->record->maintenance_type === MaintenanceOrder::TYPE_CHECKOUT;
    }

    public function getOperationLabel(): string
    {
        return $this->isOperationCheckout()
            ? 'Check-out (Desmobilização)'
            : 'Check-in (Mobilização)';
    }

    public function getEvidencesByCategory()
    {
        return $this->record->evidences->groupBy(function ($evidence) {
            if (filled($evidence->category)) {
                return $evidence->category;
            }

            return match ($evidence->evidence_type) {
                'antes' => 'Estado Inicial',
                'depois' => 'Estado Final',
                default => 'Evidência',
            };
        });
    }

    public function isFullyAudited(): bool
    {
        return $this->record->evidences->isNotEmpty()
            && $this->record->evidences->every(fn ($evidence) => filled($evidence->latitude) && filled($evidence->longitude));
    }
}
