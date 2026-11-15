<?php

use App\Http\Controllers\AssistenteRelatorioController;
use App\Http\Controllers\AssistenteSessionController;
use App\Http\Controllers\Chat\ChatAuthenticatedSessionController;
use App\Http\Controllers\Chat\ChatMessageSyncController;
use App\Livewire\GlobalChat;
use Illuminate\Support\Facades\Route;

/*
 * Chat standalone, instalável como PWA, independente do painel /admin --
 * login próprio (sempre com "remember" ligado, ver
 * ChatAuthenticatedSessionController), reaproveita App\Livewire\GlobalChat
 * (mesma trait InteractsWithChat que já alimenta a bolha flutuante do
 * painel), só com layout dedicado sem sidebar do Filament.
 */
Route::prefix('chat')->name('chat.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [ChatAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [ChatAuthenticatedSessionController::class, 'store']);
    });

    /*
     * GET / (start_url do manifest-chat.json) fica FORA do middleware
     * chat.auth de propósito: um PWA instalado (display: standalone) que
     * segue um redirect HTTP logo no start_url costuma travar em tela
     * preta/branca na primeira abertura em vários WebViews Android/iOS --
     * bug real reproduzido 2026-08-12. GlobalChat::mount() resolve
     * guest vs autenticado internamente (mesmo response 200), sem
     * redirect de navegação de topo.
     */
    Route::get('/', GlobalChat::class)->name('index');

    Route::middleware('chat.auth')->group(function () {
        Route::post('/logout', [ChatAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::post('/messages/sync', [ChatMessageSyncController::class, 'store'])->name('messages.sync');
    });
});

/*
 * App instalavel (PWA) so com o Assistente de Prospeccao -- o mesmo chat da
 * Central/Prospects em tela cheia, pra usar no celular. So super admin.
 * GET / nao redireciona (mesmo motivo do /chat: start_url de PWA que
 * redireciona trava em tela em branco em alguns WebViews): a view resolve
 * visitante / sem permissao / autorizado sozinha.
 */
Route::prefix('assistente')->name('assistente.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AssistenteSessionController::class, 'create'])->name('login');
        Route::post('/login', [AssistenteSessionController::class, 'store'])->middleware('throttle:10,1');
    });

    Route::get('/', fn () => view('assistente.index'))->name('index');
    Route::get('/suprimentos', fn () => view('assistente.suprimentos'))->name('suprimentos');

    // Relatorio compartilhavel (WhatsApp): publico, so pelo token longo + validade.
    Route::get('/r/{token}', [AssistenteRelatorioController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:60,1')->name('relatorio');

    Route::post('/logout', [AssistenteSessionController::class, 'destroy'])->middleware('auth')->name('logout');
});
