<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenanceOrderDossieController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalDemoController;
use App\Livewire\EquipmentDamageMobile;
use App\Livewire\EquipmentMovementMobile;
use App\Livewire\MaintenanceChecklistMobile;
use App\Models\Asset;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\MaintenanceOrder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::redirect('/admin/innova/categories', '/admin/innova/bill-categories');
Route::get('/', fn () => redirect()->to('/admin'));

Route::get('/admin/app/{tenant}/maintenance-report', [MaintenanceReportController::class, 'show'])
    ->name('maintenance.report')
    ->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/trocar-senha', fn () => view('auth.trocar-senha'))->name('admin.trocar-senha');
    Route::post('/admin/trocar-senha', [PasswordController::class, 'update'])->name('admin.trocar-senha.update');

    Route::get('/dashboard', function () {
        $user = auth()->user();
        $tenantSlug = $user->latest_tenant_slug ?? Filament::getUserTenants($user)->first()?->slug ?? $user->tenant?->slug ?? $user->tenant_id;

        return $tenantSlug ? redirect()->route('filament.admin.pages.dashboard', ['tenant' => $tenantSlug]) : redirect()->to('/admin');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/locacao/laudo-minimalista/{order}', [RentalDemoController::class, 'laudoMinimalista'])->name('rental-demo.laudo-minimalista');

    Route::get('/admin/maintenance-orders/{record}/dossie-pdf', [MaintenanceOrderDossieController::class, 'download'])
        ->name('maintenance-orders.dossie.pdf');

    Route::get('/admin/maintenance-orders/{order}/laudo-minimalista', [MaintenanceOrderController::class, 'laudoMinimalista'])
        ->name('maintenance-orders.laudo-minimalista');

    Route::get('/admin/maintenance-orders/{id}/print', function ($id) {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            abort(403);
        }
        $order = MaintenanceOrder::where('tenant_id', $tenant->id)->with(['asset', 'client', 'technician', 'checklists', 'materials.material'])->findOrFail($id);

        return view('maintenance-orders.print', compact('order'));
    })->name('maintenance-orders.print');

    Route::get('/admin/maintenance-orders/{maintenanceOrder}/checklist-digital', MaintenanceChecklistMobile::class)
        ->name('maintenance-orders.checklist-mobile');

    Route::get('/admin/maintenance-orders/{maintenanceOrder}/movimentacao/{type}', EquipmentMovementMobile::class)
        ->where('type', 'mobilizacao|desmobilizacao')
        ->name('maintenance-orders.equipment-movement-mobile');

    Route::get('/admin/equipment-movements/{equipmentMovement}/avarias/create', EquipmentDamageMobile::class)
        ->name('equipment-movements.damages.create');

    // --- ROTA UNIFICADA DE IMPRESSÃO (AJUSTADA) ---
    Route::get('/admin/app/{tenant}/assets/{asset}/print-qr', function ($tenant, Asset $asset) {
        $editUrl = route('filament.admin.resources.assets.edit', [
            'tenant' => $tenant,
            'record' => $asset->id,
        ]);

        // Geramos o SVG. Caso a biblioteca falhe, retorna um placeholder simples
        $qrCodeSvg = QrCode::format('svg')->size(150)->margin(1)->generate($editUrl);

        return view('print.print-qr', compact('asset', 'qrCodeSvg'));
    })->name('asset.print-qr');

    Route::get('/admin/app/{tenant}/maintenance/chat/canvas', fn () => view('maintenance.chat-canvas'))->name('maintenance.chat.canvas');

    Route::get('/admin/app/{tenant}/maintenance/chat/{record}/print', function ($tenant, $record) {
        $room = ChatRoom::findOrFail($record);
        $messages = ChatMessage::where('chat_room_id', $record)->with('user')->oldest()->get();

        return view('maintenance.chat-print', compact('room', 'messages'));
    })->name('maintenance.chat.print');
});

require __DIR__.'/auth.php';
