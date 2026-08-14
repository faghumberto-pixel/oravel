<?php

use App\Http\Controllers\AsaasCheckoutController;
use Illuminate\Support\Facades\Route;

/*
 * Autoatendimento: cliente clica "Assinar" num plano no site institucional
 * (oravel.com.br) e cai aqui pra se cadastrar sozinho -- sem depender de
 * um operador criando o Tenant manualmente no painel Central (fluxo que
 * já existia, TenantResource\Pages\CreateTenant). 'guest' porque é pra
 * quem ainda não tem conta -- a rota 'register' padrão do Laravel foi
 * desativada de propósito neste projeto (redireciona pro login do
 * Filament, ver routes/auth.php), então precisa de uma rota própria.
 */
Route::middleware('guest')->group(function () {
    Route::get('/assinar', [AsaasCheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/assinar', [AsaasCheckoutController::class, 'store'])->name('checkout.store');
});
