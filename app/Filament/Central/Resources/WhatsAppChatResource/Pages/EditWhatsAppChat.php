<?php

namespace App\Filament\Central\Resources\WhatsAppChatResource\Pages;

use App\Filament\Central\Resources\WhatsAppChatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppChat extends EditRecord
{
    protected static string $resource = WhatsAppChatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
