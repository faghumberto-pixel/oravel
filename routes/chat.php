<?php

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

    Route::middleware('chat.auth')->group(function () {
        Route::get('/', GlobalChat::class)->name('index');
        Route::post('/logout', [ChatAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::post('/messages/sync', [ChatMessageSyncController::class, 'store'])->name('messages.sync');
    });
});
