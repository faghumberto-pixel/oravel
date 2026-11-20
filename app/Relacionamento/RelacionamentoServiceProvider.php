<?php

namespace App\Relacionamento;

use App\Relacionamento\Console\BackfillRelacionamento;
use App\Relacionamento\Console\PurgarRelacionamento;
use App\Relacionamento\Http\RelacionamentoEventoController;
use App\Relacionamento\Http\RelacionamentoSiteEventoController;
use App\Relacionamento\Http\SenderWebhookController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Modulo Relacionamento Oravel: registra seu proprio comando e rota, sem editar routes/api.php. */
class RelacionamentoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BackfillRelacionamento::class, PurgarRelacionamento::class]);
        }

        // Cumpre o prazo de guarda da politica de privacidade, todo dia de madrugada.
        $this->callAfterResolving(Schedule::class, fn (Schedule $s) => $s->command('relacionamento:purgar')->dailyAt('03:30')->withoutOverlapping());

        Route::middleware(['api', 'throttle:120,1'])->prefix('api')->group(function () {
            Route::post('/relacionamento/eventos', [RelacionamentoEventoController::class, 'store'])
                ->name('relacionamento.eventos.store');

            // Sinal anonimo do navegador do oravel.org (sem token; barreira = Origin + anonimo + throttle por IP).
            Route::post('/relacionamento/site-evento', [RelacionamentoSiteEventoController::class, 'store'])
                ->middleware('throttle:60,1')
                ->name('relacionamento.site-evento.store');

            // Webhooks do Sender: um caminho por topico (o corpo nao traz o nome do evento). Assinatura HMAC obrigatoria.
            Route::post('/relacionamento/sender/{topico}', [SenderWebhookController::class, 'store'])
                ->whereIn('topico', SenderWebhookController::TOPICOS)
                ->middleware('throttle:120,1')
                ->name('relacionamento.sender.store');
        });
    }
}
