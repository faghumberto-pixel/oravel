<?php

namespace App\Filament\Resources\DocumentSignatureResource\Pages;

use App\Filament\Resources\DocumentSignatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentSignatures extends ListRecords
{
    protected static string $resource = DocumentSignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Assinatura')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTitle(): string
    {
        return 'Assinaturas Eletrônicas';
    }
}
