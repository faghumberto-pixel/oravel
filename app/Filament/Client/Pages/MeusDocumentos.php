<?php

namespace App\Filament\Client\Pages;

use App\Models\Tenant;
use Filament\Pages\Page;

class MeusDocumentos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.client.pages.meus-documentos';

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?int $navigationSort = 4;

    public ?Tenant $tenant = null;

    public function mount(): void
    {
        $this->tenant = auth('client')->user()?->tenant;
    }

    public function getViewData(): array
    {
        return [
            'signature' => $this->tenant?->signature,
            'signature_required_by' => $this->tenant?->signature_required_by,
            'has_signed' => $this->tenant?->signature !== null,
        ];
    }
}
