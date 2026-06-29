<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AccountPayableResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('accounts_payable')]
class CreateAccountPayable extends CreateRecord
{
    protected static string $resource = AccountPayableResource::class;
}
