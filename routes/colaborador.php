<?php

use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\ColaboradorNotificacoesController;
use App\Http\Controllers\ColaboradorOfflineController;
use App\Http\Controllers\ColaboradorOsController;
use App\Livewire\AssetDossierMobile;
use App\Livewire\MaintenanceOrderFieldWizard;
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
    Route::get('/offline', [ColaboradorController::class, 'offline'])->name('offline');
    // Não pode se chamar /sw.js: existe public/sw.js (outro app) e o nginx serve o arquivo físico antes do Laravel.
    Route::get('/colaborador-sw.js', [ColaboradorNotificacoesController::class, 'serviceWorker'])->name('sw');
    Route::get('/entrar', [ColaboradorController::class, 'loginForm'])->name('login');
    Route::post('/entrar', [ColaboradorController::class, 'login'])->name('login.post');

    Route::middleware('colab.auth')->group(function () {
        Route::get('/', [ColaboradorController::class, 'home'])->name('home');
        Route::post('/sair', [ColaboradorController::class, 'logout'])->name('logout');
        Route::get('/dossie/{assetId?}', AssetDossierMobile::class)->name('dossie');
        // Trabalho sem internet: o celular baixa o pacote e envia a fila (caminhos com /api/ nunca passam pelo cache do SW).
        Route::get('/api/pacote', [ColaboradorOfflineController::class, 'pacote'])->name('api.pacote');
        Route::get('/api/csrf', [ColaboradorOfflineController::class, 'csrf'])->name('api.csrf');
        Route::post('/api/fila', [ColaboradorOfflineController::class, 'fila'])->name('api.fila');
        Route::get('/os/{id}/offline', [ColaboradorOfflineController::class, 'executarOffline'])->name('os.offline');

        Route::get('/minhas-os', [ColaboradorOsController::class, 'minhas'])->name('minhas-os');
        Route::get('/os', [ColaboradorOsController::class, 'buscar'])->name('os.buscar');
        Route::get('/os/{maintenanceOrder}/executar', MaintenanceOrderFieldWizard::class)->name('os.executar');
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
