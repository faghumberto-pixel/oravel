<?php

namespace App\Filament\Resources\ClientFeedbackResource\Pages;

use App\Filament\Resources\ClientFeedbackResource;
use Filament\Resources\Pages\ManageRecords;

class ManageClientFeedbacks extends ManageRecords
{
    protected static string $resource = ClientFeedbackResource::class;

    protected static ?string $title = 'Voz do Cliente';
}
