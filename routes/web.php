<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssetPublicController;
use App\Models\Asset;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\MaintenanceOrderController;
// --- INTERVENÇÃO MÍNIMA: IMPORTAÇÃO ADICIONAL PARA DEMO (SEM EXCLUSÃO) ---
use App\Http\Controllers\RentalDemoController;
// --- ADITIVO: IMPORTAÇÃO DO MOTOR DE PDF (DOSSIÊ) ---
use App\Http\Controllers\MaintenanceOrderDossieController;

// Rota para a Demonstração da "Gestão de Locação"
// Em produção, seria algo como /gestao-locacao/dashboard, mas para a demo
// vamos focar na visualização de uma OS específica para mostrar as evidências.
// Esta rota genérica original foi mantida intacta.
Route::get('/gestao-locacao/os/{order}', [MaintenanceOrderController::class, 'showDashboardDemo'])
     ->name('gestao-locacao.os.show');

/*
|--------------------------------------------------------------------------
| Web Routes (Oravel)
|--------------------------------------------------------------------------
*/

// Rota de leitura pública (QR Code)
Route::get('/scan/{asset}', [AssetPublicController::class, 'show'])->name('asset.scan');

// Rota pública para impressão da etiqueta QR Code
Route::get('/scan/{asset}/print', function ($uuid) {
    $asset = Asset::findOrFail($uuid);
    return view('assets.print-qr', compact('asset'));
})->name('asset.print');

// Rotas protegidas
Route::middleware(['auth', 'verified'])->group(function () {
    
    // REDIRECIONAMENTO PARA O FILAMENT: 
    // Em vez de carregar a view dashboard.blade.php, enviamos para o painel administrativo
    Route::get('/dashboard', function () {
        return redirect()->route('filament.admin.pages.dashboard');
    })->name('dashboard');

    // Rotas de perfil mantidas
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- CORREÇÃO CIRÚRGICA: INÍCIO (Issue #RouteNotFound na Demo) ---
    // Rota Missing: Linka o botão do Filament à view minimalista simulada.
    // (Puramente aditivo, focado na demo presencial).
    Route::get('/locacao/laudo-minimalista/{order}', [RentalDemoController::class, 'laudoMinimalista'])
         ->name('rental-demo.laudo-minimalista');
    // --- CORREÇÃO CIRÚRGICA: FIM ---

    // --- ADITIVO: Rota de Geração do Laudo/Dossiê em PDF ---
    // Esta rota conecta o botão "Imprimir Laudo" do Filament ao Controller de PDF.
    Route::get('/maintenance-orders/{record}/dossie', [MaintenanceOrderDossieController::class, 'download'])
         ->name('maintenance-orders.dossie.pdf');
});

// Autenticação
require __DIR__.'/auth.php';

// Rota raiz redirecionando para o ambiente de gestão
Route::get('/', function () {
    return redirect()->route('dashboard');
});