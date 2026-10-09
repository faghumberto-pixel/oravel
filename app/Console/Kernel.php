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
        // Lembretes de cobrança por WhatsApp (só empresas que ligaram o aviso). Em PROD precisa de schedule:run no cron.
        $schedule->command('whatsapp:avisos-cobranca')->dailyAt('09:00');
        $schedule->command('site-visits:close-stale')->everyFiveMinutes();
        // Mantém só os 50 acessos mais recentes (pedido do usuário 2026-09-27).
        $schedule->command('site-visits:prune')->hourly();
        $schedule->command('employees:check-certification-expirations')->daily();
        $schedule->command('epi:check-ca-expirations')->daily();
        $schedule->command('epi:check-lifespan-expirations')->daily();
        $schedule->command('nr13:check-expirations')->daily();
        $schedule->command('contracts:calculate-overage')->monthlyOn(1, '03:00');
        // 30min depois de propósito: reaproveita o excedente já calculado
        // acima em vez de recalcular (ver ContractMeasurementService).
        $schedule->command('contracts:generate-measurements')->monthlyOn(1, '03:30');

        // GPS (Traccar) alimentando o odômetro dos veículos da frota. Em PROD este comando também precisa de uma linha própria no cron.
        $schedule->command('frota:sincronizar-gps')->hourly();

        // Sincroniza status de pagamentos com Asaas a cada 30 minutos
        $schedule->command('asaas:sync-payment-status')->everyThirtyMinutes();
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
