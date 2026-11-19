<?php

namespace App\Relacionamento;

use App\Relacionamento\Console\BackfillRelacionamento;
use App\Relacionamento\Http\RelacionamentoEventoController;
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
        });
    }
}
