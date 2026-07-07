<?php

namespace App\Observers;

use App\Models\CrmLeadInteraction;

class CrmLeadInteractionObserver
{
    public function saved(CrmLeadInteraction $interaction): void
    {
        $interaction->lead->refreshFollowUpCache();
    }

    public function deleted(CrmLeadInteraction $interaction): void
    {
        $interaction->lead->refreshFollowUpCache();
    }
}
