<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\ContractTimelineService;

class ContractTimelinePrintController extends Controller
{
    public function __invoke(Contract $contract)
    {
        $service = new ContractTimelineService();
        $timelineData = $service->getTimelineData($contract);

        return view('contract-timeline-print', [
            'contract' => $contract,
            'timelineData' => $timelineData,
        ]);
    }
}
