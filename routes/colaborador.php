<?php

use App\Http\Controllers\ColaboradorController;
use App\Livewire\AssetDossierMobile;
use Illuminate\Support\Facades\Route;

/*
| App do colaborador: link próprio (subdomínio em PROD, /colaborador em DEV), só no celular, login com o mesmo
| usuário do sistema. Novas funções entram em App\Support\Colaborador\Modulos + uma rota aqui.
*/
Route::middleware(['web', 'colab.mobile'])->name('colab.')->group(function () {
    Route::get('/manifest.webmanifest', [ColaboradorController::class, 'manifest'])->name('manifest');
    Route::get('/entrar', [ColaboradorController::class, 'loginForm'])->name('login');
    Route::post('/entrar', [ColaboradorController::class, 'login'])->name('login.post');

    Route::middleware('colab.auth')->group(function () {
        Route::get('/', [ColaboradorController::class, 'home'])->name('home');
        Route::post('/sair', [ColaboradorController::class, 'logout'])->name('logout');
        Route::get('/dossie/{assetId?}', AssetDossierMobile::class)->name('dossie');
        Route::get('/escanear', [ColaboradorController::class, 'escanear'])->name('escanear');
        Route::get('/ponto', [ColaboradorController::class, 'ponto'])->name('ponto');
    });
});
