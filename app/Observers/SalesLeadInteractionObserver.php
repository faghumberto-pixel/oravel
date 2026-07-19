<?php

namespace App\Observers;

use App\Models\SalesLeadInteraction;

class SalesLeadInteractionObserver
{
    public function saved(SalesLeadInteraction $interaction): void
    {
        $interaction->lead->refreshInteractionCache();
    }

    public function deleted(SalesLeadInteraction $interaction): void
    {
        $interaction->lead->refreshInteractionCache();
    }
}
