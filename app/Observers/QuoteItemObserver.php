<?php

namespace App\Observers;

use App\Models\QuoteItem;

class QuoteItemObserver
{
    public function saved(QuoteItem $item): void
    {
        $item->quote->recalculateTotal();
    }

    public function deleted(QuoteItem $item): void
    {
        $item->quote->recalculateTotal();
    }
}
