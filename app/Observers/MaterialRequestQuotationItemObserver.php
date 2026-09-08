<?php

namespace App\Observers;

use App\Models\MaterialRequestQuotationItem;

class MaterialRequestQuotationItemObserver
{
    public function saved(MaterialRequestQuotationItem $item): void
    {
        $item->quotation->recalculateTotal();
    }

    public function deleted(MaterialRequestQuotationItem $item): void
    {
        $item->quotation->recalculateTotal();
    }
}
