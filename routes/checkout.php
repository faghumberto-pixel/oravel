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
    Route::get('/assinar/pendente', fn () => view('checkout.pending'))->name('checkout.pending');

    // callback do Checkout da Asaas (POST /v3/checkouts, ver
    // AsaasService::createTenantCheckout()) -- só controla a experiência
    // do usuário, NUNCA confirma pagamento (a Asaas é explícita sobre
    // isso: "não considere successUrl como confirmação financeira"). A
    // liberação de acesso real acontece só via webhook (CHECKOUT_PAID),
    // ver AsaasWebhookController.
    Route::get('/assinar/sucesso', fn () => view('checkout.success'))->name('checkout.success');
    Route::get('/assinar/cancelado', fn () => view('checkout.cancelled'))->name('checkout.cancelled');

    // Chamada só depois que o Contrato de Assinatura (assinatura
    // eletrônica, /assinatura/{token}) foi confirmado -- é aqui que o
    // Checkout de pagamento é de fato criado (2026-09-23, ver
    // AsaasCheckoutController::continueAfterSignature()).
    Route::get('/assinar/continuar/{token}', [AsaasCheckoutController::class, 'continueAfterSignature'])->name('checkout.continue');
});
