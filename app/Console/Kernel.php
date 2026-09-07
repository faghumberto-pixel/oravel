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
        $schedule->command('financeiro:marcar-contas-atrasadas')->dailyAt('01:00');
        $schedule->command('site-visits:close-stale')->everyFiveMinutes();
        $schedule->command('employees:check-certification-expirations')->daily();
        $schedule->command('contracts:calculate-overage')->monthlyOn(1, '03:00');
        // 30min depois de propósito: reaproveita o excedente já calculado
        // acima em vez de recalcular (ver ContractMeasurementService).
        $schedule->command('contracts:generate-measurements')->monthlyOn(1, '03:30');

        // Backup diário do banco (spatie/laravel-backup). Só dump do banco,
        // não do código: o código já está no git e o deploy.sh já copia a
        // aplicação inteira a cada deploy. Clean antes do run para não
        // arriscar encher o disco (já aconteceu em 2026-07, ver deploy.sh).
        $schedule->command('backup:clean')->daily()->at('01:00')->onOneServer();
        $schedule->command('backup:run --only-db')->daily()->at('01:15')->onOneServer();
        $schedule->command('backup:monitor')->daily()->at('02:00')->onOneServer();
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
