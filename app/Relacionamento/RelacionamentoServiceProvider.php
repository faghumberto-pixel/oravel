<?php

namespace App\Relacionamento;

use App\Relacionamento\Console\BackfillRelacionamento;
use App\Relacionamento\Http\RelacionamentoEventoController;
use App\Relacionamento\Http\RelacionamentoSiteEventoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Modulo Relacionamento Oravel: registra seu proprio comando e rota, sem editar routes/api.php. */
class RelacionamentoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BackfillRelacionamento::class]);
        }

        Route::middleware(['api', 'throttle:120,1'])->prefix('api')->group(function () {
            Route::post('/relacionamento/eventos', [RelacionamentoEventoController::class, 'store'])
                ->name('relacionamento.eventos.store');

            // Sinal anonimo do navegador do oravel.org (sem token; barreira = Origin + anonimo + throttle por IP).
            Route::post('/relacionamento/site-evento', [RelacionamentoSiteEventoController::class, 'store'])
                ->middleware('throttle:60,1')
                ->name('relacionamento.site-evento.store');
        });
    }
}
