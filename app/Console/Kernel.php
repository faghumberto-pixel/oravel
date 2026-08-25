<?php

namespace App\Console;

use App\Console\Commands\TestSetupCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Adicione seu comando aqui
        TestSetupCommand::class, // <-- ESTA LINHA É CRÍTICA
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('maintenance:check-due-alerts')->daily();
        $schedule->command('sales:notify-appointments')->everyFiveMinutes();
        $schedule->command('financeiro:verificar-vencimentos')->daily();
        $schedule->command('site-visits:close-stale')->everyFiveMinutes();
        $schedule->command('employees:check-certification-expirations')->daily();
        $schedule->command('contracts:calculate-overage')->monthlyOn(1, '03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
