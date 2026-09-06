<?php

namespace App\Filament\Resources\DocumentSignatureResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\DocumentSignatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentSignature extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = DocumentSignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Actions\EditAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return "Assinatura - {$this->record->signer_name}";
    }
}
