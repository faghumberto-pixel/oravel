<?php

use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\ColaboradorNotificacoesController;
use App\Http\Controllers\ColaboradorOsController;
use App\Livewire\AssetDossierMobile;
use Illuminate\Support\Facades\Route;

/*
| App do colaborador: link próprio (subdomínio em PROD, /colaborador em DEV), só no celular, login com o mesmo
| usuário do sistema. Novas funções entram em App\Support\Colaborador\Modulos + uma rota aqui.
*/
// Subdomínio próprio quando COLABORADOR_HOST existe; senão /colaborador (DEV).
$host = config('colaborador.host');
$grupo = $host ? Route::domain($host) : Route::prefix('colaborador');

$grupo->middleware(['web', 'colab.mobile'])->name('colab.')->group(function () {
    Route::get('/manifest.webmanifest', [ColaboradorController::class, 'manifest'])->name('manifest');
    Route::get('/sw.js', [ColaboradorNotificacoesController::class, 'serviceWorker'])->name('sw');
    Route::get('/entrar', [ColaboradorController::class, 'loginForm'])->name('login');
    Route::post('/entrar', [ColaboradorController::class, 'login'])->name('login.post');

    Route::middleware('colab.auth')->group(function () {
        Route::get('/', [ColaboradorController::class, 'home'])->name('home');
        Route::post('/sair', [ColaboradorController::class, 'logout'])->name('logout');
        Route::get('/dossie/{assetId?}', AssetDossierMobile::class)->name('dossie');
        Route::get('/minhas-os', [ColaboradorOsController::class, 'minhas'])->name('minhas-os');
        Route::get('/os', [ColaboradorOsController::class, 'buscar'])->name('os.buscar');
        Route::get('/os/ativo/{id}', [ColaboradorOsController::class, 'doAtivo'])->name('os.ativo');
        Route::get('/os/{id}', [ColaboradorOsController::class, 'mostrar'])->name('os');
        Route::get('/escanear', [ColaboradorController::class, 'escanear'])->name('escanear');
        Route::get('/ponto', [ColaboradorController::class, 'ponto'])->name('ponto');

        Route::get('/notificacoes', [ColaboradorNotificacoesController::class, 'index'])->name('notificacoes');
        Route::post('/notificacoes/limpar', [ColaboradorNotificacoesController::class, 'limpar'])->name('notificacoes.limpar');
        Route::post('/push/assinar', [ColaboradorNotificacoesController::class, 'assinar'])->name('push.assinar');
        Route::post('/push/cancelar', [ColaboradorNotificacoesController::class, 'cancelar'])->name('push.cancelar');
    });
});
