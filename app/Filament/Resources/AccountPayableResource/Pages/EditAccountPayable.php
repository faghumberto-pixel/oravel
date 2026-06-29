<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AccountPayableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('accounts_payable')]
class EditAccountPayable extends EditRecord
{
    protected static string $resource = AccountPayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
