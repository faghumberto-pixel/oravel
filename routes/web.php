<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalDemoController;
use Filament\Facades\Filament;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Aqui é onde você pode registrar as rotas web para sua aplicação.
|
 */

// Rota raiz (Adapte se você tiver uma landing page ou tela de login direta)
Route::get('/', function () {
    return redirect()->to('/admin');
});

// ==========================================
// ROTAS PROTEGIDAS POR AUTENTICAÇÃO
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () {
    
    /**
     * 🛡️ REDIRECIONAMENTO INTELIGENTE FILAMENT MULTITENANT (Oravel Premium)
     * Captura o contexto do tenant usando a fachada do Filament para evitar BadMethodCallException
     */
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        // Resolve o Tenant buscando primeiro propriedades diretas ou os métodos do painel do Filament
        $tenantSlug = $user->latest_tenant_slug 
            ?? Filament::getUserTenants($user)->first()?->slug
            ?? Filament::getUserTenants($user)->first()?->id
            ?? $user->tenant?->slug 
            ?? $user->tenant_id;

        // Se o usuário não tiver nenhum vínculo de tenant mapeado, joga para a raiz do painel administrativo
        if (! $tenantSlug) {
            return redirect()->to('/admin');
        }

        return redirect()->route('filament.admin.pages.dashboard', ['tenant' => $tenantSlug]);
    })->name('dashboard');

    // Rotas de gerenciamento de perfil padrão
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Rota cirúrgica do laudo minimalista preservada
    Route::get('/locacao/laudo-minimalista/{order}', [RentalDemoController::class, 'laudoMinimalista'])
         ->name('rental-demo.laudo-minimalista');
});

// Autenticação padrão do Laravel (se utilizado em paralelo ao Filament)
require __DIR__.'/auth.php';