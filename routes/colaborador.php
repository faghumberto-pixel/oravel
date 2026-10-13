<?php

use App\Http\Controllers\ColaboradorAusenciasController;
use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\ColaboradorLogisticaController;
use App\Http\Controllers\ColaboradorNotificacoesController;
use App\Http\Controllers\ColaboradorOfflineController;
use App\Http\Controllers\ColaboradorOsController;
use App\Livewire\AbastecimentoMobile;
use App\Livewire\AssetDossierMobile;
use App\Livewire\MaintenanceOrderFieldWizard;
use App\Livewire\MarcarKmMobile;
use App\Livewire\MotoristaVeiculoMobile;
use App\Livewire\SaidaVeiculoMobile;
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

        // Logística: início com as funções e uma tela por função (todas aceitam o veículo direto pelo QR: .../{assetId}).
        Route::get('/logistica', [ColaboradorLogisticaController::class, 'hub'])->name('logistica');
        Route::get('/logistica/km-rodado', [ColaboradorLogisticaController::class, 'kmRodado'])->name('logistica.km-rodado');
        Route::get('/logistica/vencimentos', [ColaboradorLogisticaController::class, 'vencimentos'])->name('logistica.vencimentos');
        Route::get('/logistica/saida/{assetId?}', SaidaVeiculoMobile::class)->name('logistica.saida');
        Route::get('/logistica/abastecimento/{assetId?}', AbastecimentoMobile::class)->name('logistica.abastecimento');
        Route::get('/logistica/km/{assetId?}', MarcarKmMobile::class)->name('logistica.km');
        Route::get('/logistica/motorista/{assetId?}', MotoristaVeiculoMobile::class)->name('logistica.motorista');
        Route::get('/minhas-os', [ColaboradorOsController::class, 'minhas'])->name('minhas-os');
        Route::get('/os', [ColaboradorOsController::class, 'buscar'])->name('os.buscar');
        Route::get('/os/{maintenanceOrder}/executar', MaintenanceOrderFieldWizard::class)->name('os.executar');
        Route::get('/os/ativo/{id}', [ColaboradorOsController::class, 'doAtivo'])->name('os.ativo');
        Route::get('/os/{id}', [ColaboradorOsController::class, 'mostrar'])->name('os');
        Route::get('/escanear', [ColaboradorController::class, 'escanear'])->name('escanear');
        Route::get('/ponto', [ColaboradorController::class, 'ponto'])->name('ponto');

        // Faltas e ausências: comunicar (foto do atestado, CID, explicação) e ver o próprio histórico.
        Route::get('/ausencias', [ColaboradorAusenciasController::class, 'index'])->name('ausencias');
        Route::get('/ausencias/nova', [ColaboradorAusenciasController::class, 'nova'])->name('ausencias.nova');
        Route::post('/ausencias', [ColaboradorAusenciasController::class, 'salvar'])->name('ausencias.salvar');
        Route::get('/ausencias/{id}/anexo/{indice}', [ColaboradorAusenciasController::class, 'anexo'])->whereNumber('indice')->name('ausencias.anexo');

        Route::get('/notificacoes', [ColaboradorNotificacoesController::class, 'index'])->name('notificacoes');
        Route::post('/notificacoes/limpar', [ColaboradorNotificacoesController::class, 'limpar'])->name('notificacoes.limpar');
        Route::post('/push/assinar', [ColaboradorNotificacoesController::class, 'assinar'])->name('push.assinar');
        Route::post('/push/cancelar', [ColaboradorNotificacoesController::class, 'cancelar'])->name('push.cancelar');
    });
});
