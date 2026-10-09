<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tarefas agendadas
|--------------------------------------------------------------------------
| No Laravel 11+ o agendador lê ESTE arquivo (ou withSchedule em bootstrap/app.php); o método schedule()
| de App\Console\Kernel não é mais usado -- por isso estas tarefas nunca rodaram antes (2026-10-09).
| Em PRODUÇÃO precisa da linha de cron: * * * * * php artisan schedule:run
| withoutOverlapping evita acumular execuções se alguma demorar.
*/
Schedule::command('maintenance:check-due-alerts')->daily()->withoutOverlapping();
Schedule::command('sales:notify-appointments')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('financeiro:verificar-vencimentos')->daily()->withoutOverlapping();
Schedule::command('financeiro:marcar-contas-atrasadas')->dailyAt('01:00')->withoutOverlapping();
// Lembretes de cobrança por WhatsApp: só empresas com o módulo WhatsApp liberado e o aviso ligado.
Schedule::command('whatsapp:avisos-cobranca')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('site-visits:close-stale')->everyFiveMinutes()->withoutOverlapping();
// Mantém só os 50 acessos mais recentes (pedido do usuário 2026-09-27).
Schedule::command('site-visits:prune')->hourly()->withoutOverlapping();
Schedule::command('employees:check-certification-expirations')->daily()->withoutOverlapping();
Schedule::command('epi:check-ca-expirations')->daily()->withoutOverlapping();
Schedule::command('epi:check-lifespan-expirations')->daily()->withoutOverlapping();
Schedule::command('nr13:check-expirations')->daily()->withoutOverlapping();
Schedule::command('contracts:calculate-overage')->monthlyOn(1, '03:00')->withoutOverlapping();
// 30 min depois de propósito: reaproveita o excedente já calculado (ver ContractMeasurementService).
Schedule::command('contracts:generate-measurements')->monthlyOn(1, '03:30')->withoutOverlapping();
// GPS (Traccar) alimentando o odômetro da frota (antes tinha linha própria no cron).
Schedule::command('frota:sincronizar-gps')->hourly()->withoutOverlapping();
// Sincroniza status de pagamentos com o Asaas a cada 30 minutos.
Schedule::command('asaas:sync-payment-status')->everyThirtyMinutes()->withoutOverlapping();
// Limpeza semanal de notificações e registros automáticos antigos (domingo de madrugada).
Schedule::command('registros:limpar')->weeklyOn(0, '04:00')->withoutOverlapping();

