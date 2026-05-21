<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalDemoController;
use App\Models\MaintenanceOrder;
use Filament\Facades\Filament;

// Redirecionamento raiz
Route::get('/', fn () => redirect()->to('/admin'));

Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard com redirecionamento de tenant
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $tenantSlug = $user->latest_tenant_slug 
            ?? Filament::getUserTenants($user)->first()?->slug
            ?? Filament::getUserTenants($user)->first()?->id
            ?? $user->tenant?->slug 
            ?? $user->tenant_id;

        return $tenantSlug ? redirect()->route('filament.admin.pages.dashboard', ['tenant' => $tenantSlug]) : redirect()->to('/admin');
    })->name('dashboard');

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Laudo
    Route::get('/locacao/laudo-minimalista/{order}', [RentalDemoController::class, 'laudoMinimalista'])->name('rental-demo.laudo-minimalista');

    // Rota de Impressão (NOME CORRIGIDO: maintenance-orders.print)
    Route::get('/admin/maintenance-orders/{id}/print', function ($id) {
        $tenantId = Filament::getTenant()?->id;
        
        $order = MaintenanceOrder::where('tenant_id', $tenantId)
            ->with(['asset', 'client', 'technician', 'checklists', 'materials.material'])
            ->findOrFail($id);

        return view('maintenance-orders.print', compact('order'));
    })->name('maintenance-orders.print');
});

require __DIR__.'/auth.php';