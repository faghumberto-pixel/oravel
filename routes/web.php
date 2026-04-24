<?php

use App\Http\Controllers\ProfileController;
use App\Models\Asset;
use Illuminate\Support\Facades\Route;

// Remova a rota "/" se ela estiver conflitando, ou mova para o final.
// Vamos focar no acesso ao Dashboard.

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        // Agora, com o AuthenticatedSessionController ajustado, 
        // a session('tenant_id') já estará aqui.
        if (!session()->has('tenant_id')) {
            return "Erro: Sessão de tenant não encontrada. Tente logar novamente.";
        }
        
        $assets = Asset::all();
        return view('dashboard', ['assets' => $assets]);
    })->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Importante: as rotas do Breeze devem estar aqui
require __DIR__.'/auth.php';

// Rota raiz (deixe por último)
Route::get('/', function () {
    return redirect()->route('dashboard');
});