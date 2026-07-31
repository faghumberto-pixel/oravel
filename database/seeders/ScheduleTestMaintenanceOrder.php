<?php

namespace Database\Seeders;

use App\Models\MaintenanceOrder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ScheduleTestMaintenanceOrder extends Seeder
{
    public function run(): void
    {
        $order = MaintenanceOrder::where('os_number', 'OS-TEST-001')->first();

        if ($order) {
            $order->update(['scheduled_at' => Carbon::now()->addDays(1)->setHour(11)->setMinute(0)]);
            echo "✓ O.S. OS-TEST-001 agendada para amanhã às 11:00\n";
        }
    }
}
