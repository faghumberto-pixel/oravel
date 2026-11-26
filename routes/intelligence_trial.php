<?php

use App\Http\Controllers\IntelligenceExemploController;
use App\Http\Controllers\IntelligencePlanoController;
use App\Http\Controllers\IntelligenceTrialController;
use Illuminate\Support\Facades\Route;

/*
 * Teste grátis do Oravel Intelligence: cadastro com e-mail confirmado e senha; o acesso é pela tela de login.
 * Contratação e conta ficam em routes abaixo (autenticadas).
 */
$limite = 'throttle:'.max(1, (int) config('intelligence_trial.rate_limit_por_minuto', 5)).',1';

Route::prefix('intelligence')->name('intelligence.trial.')->group(function () use ($limite) {
    Route::get('/teste-gratis', [IntelligenceTrialController::class, 'create'])->name('create');
    Route::post('/teste-gratis', [IntelligenceTrialController::class, 'store'])->middleware($limite)->name('store');
    Route::get('/verifique-seu-email', [IntelligenceTrialController::class, 'verifique'])->name('verifique');
    Route::get('/confirmar/{token}', [IntelligenceTrialController::class, 'confirmarTela'])->where('token', '[A-Za-z0-9]{48}')->name('confirmar');
    Route::post('/confirmar/{token}', [IntelligenceTrialController::class, 'confirmar'])->middleware($limite)->where('token', '[A-Za-z0-9]{48}')->name('confirmar.enviar');
    // Acesso por link (sem senha) NÃO existe mais. Estas rotas só redirecionam quem chega por e-mail ou favorito antigo.
    Route::get('/entrar/{token}', [IntelligenceTrialController::class, 'acessoPorLinkEncerrado'])->where('token', '[A-Za-z0-9]{48}')->name('entrar');
    Route::get('/novo-link', [IntelligenceTrialController::class, 'esqueciASenha'])->name('novo-link');
});

// Dentro do sistema (usuário logado): situação do plano, contratação, cancelamento e contrato aceito.
Route::prefix('intelligence')->name('intelligence.trial.')->middleware('auth')->group(function () {
    Route::get('/plano', [IntelligencePlanoController::class, 'show'])->name('plano');
    Route::get('/contratar', [IntelligencePlanoController::class, 'contratar'])->name('contratar');
    Route::post('/contratar', [IntelligencePlanoController::class, 'salvar'])->middleware('throttle:10,1')->name('contratar.store');
    Route::post('/exemplo/limpar', [IntelligenceExemploController::class, 'limpar'])->middleware('throttle:10,1')->name('exemplo.limpar');
    Route::post('/exemplo/gerar', [IntelligenceExemploController::class, 'gerar'])->middleware('throttle:5,1')->name('exemplo.gerar');
    Route::post('/trocar-plano', [IntelligencePlanoController::class, 'trocar'])->middleware('throttle:10,1')->name('trocar');
    Route::post('/cancelar', [IntelligencePlanoController::class, 'cancelar'])->middleware('throttle:5,1')->name('cancelar');
    Route::get('/contrato/{id}', [IntelligencePlanoController::class, 'contratoPdf'])->name('contrato');
});
