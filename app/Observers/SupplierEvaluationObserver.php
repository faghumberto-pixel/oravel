<?php

namespace App\Observers;

use App\Models\SupplierEvaluation;

class SupplierEvaluationObserver
{
    public function saved(SupplierEvaluation $evaluation): void
    {
        $evaluation->supplier->recalculateRatingAvg();
    }

    public function deleted(SupplierEvaluation $evaluation): void
    {
        $evaluation->supplier->recalculateRatingAvg();
    }
}
