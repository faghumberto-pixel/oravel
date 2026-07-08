<?php

use App\Http\Controllers\AssetDossierPdfController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ChatHistoryPdfController;
use App\Http\Controllers\EquipmentDamageReportController;
use App\Http\Controllers\MaintenanceKanbanPrintController;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenanceOrderDossieController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalDemoController;
use App\Http\Controllers\TablePrintController;
use App\Livewire\AssetDossierMobile;
use App\Livewire\EquipmentDamageMobile;
use App\Livewire\EquipmentMovementMobile;
use App\Livewire\MaintenanceChecklistMobile;
use App\Livewire\PreventiveMaintenanceMobile;
use App\Models\Asset;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\PreventiveMaintenanceExecution;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::redirect('/admin/innova/categories', '/admin/innova/bill-categories');
Route::get('/', fn () => redirect()->to('/admin'));

Route::get('/admin/app/maintenance-report', [MaintenanceReportController::class, 'show'])
    ->name('maintenance.report')
    ->middleware(['auth', 'verified']);

Route::get('/admin/app/maintenance-kanban/print', [MaintenanceKanbanPrintController::class, 'show'])
    ->name('maintenance.kanban.print')
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

    Route::get('/admin/chat/{room}/historico-pdf', [ChatHistoryPdfController::class, 'download'])
        ->name('chat.history.pdf');

    Route::get('/admin/print/tabela/{token}', [TablePrintController::class, 'show'])
        ->name('table-print.show');

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

    // Versao pro campo (celular do tecnico) do Plano de Manutencao Preventiva
    // -- checkbox simples por item do template do Grupo do Ativo.
    Route::get('/admin/maintenance-orders/{maintenanceOrder}/preventiva', PreventiveMaintenanceMobile::class)
        ->name('maintenance-orders.preventiva-mobile');

    Route::get('/admin/maintenance-orders/{maintenanceOrder}/preventiva/print', function (MaintenanceOrder $maintenanceOrder) {
        $order = $maintenanceOrder;
        $asset = $order->asset;
        abort_unless($asset?->checklist_group_id, 404);

        $executions = PreventiveMaintenanceExecution::where('maintenance_order_id', $order->id)
            ->with('technician')
            ->get()
            ->keyBy('maintenance_plan_id');

        $items = MaintenancePlan::where('checklist_group_id', $asset->checklist_group_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (MaintenancePlan $plan) => [
                'plan' => $plan,
                'execution' => $executions->get($plan->id),
                'status' => $plan->dueStatusForAsset($asset),
            ]);

        return view('maintenance-orders.preventiva-print', compact('order', 'items'));
    })->name('maintenance-orders.preventiva.print');

    Route::get('/admin/equipment-movements/{equipmentMovement}/avarias/create', EquipmentDamageMobile::class)
        ->name('equipment-movements.damages.create');

    Route::get('/admin/equipment-damages/{record}/laudo-pdf', [EquipmentDamageReportController::class, 'download'])
        ->name('equipment-damages.laudo.pdf');

    Route::get('/admin/assets/{asset}/dossie-pdf', [AssetDossierPdfController::class, 'download'])
        ->name('assets.dossier.pdf');

    // Versao pro campo/patio (celular do tecnico) do Dossie Rapido -- destino
    // do QR code do ativo, ver AssetResource::qr_code_display.
    Route::get('/admin/assets/dossie-mobile/{assetId?}', AssetDossierMobile::class)
        ->name('assets.dossier.mobile');

    // --- ROTA UNIFICADA DE IMPRESSÃO (AJUSTADA) ---
    // Botao "Imprimir Etiqueta" (EditAsset::getHeaderActions()) -- essa e a
    // rota de QR realmente usada pra etiqueta fisica, mais visivel que o QR
    // dentro da aba "Rastreabilidade". Antes gerava QR pra tela de edicao
    // do painel (pesada, nao serve pro celular no campo); agora aponta pro
    // Dossie Rapido mobile, mesmo destino do QR da aba.
    Route::get('/admin/app/{tenant}/assets/{asset}/print-qr', function ($tenant, Asset $asset) {
        $dossierUrl = route('assets.dossier.mobile', ['assetId' => $asset->id]);

        // Geramos o SVG. Caso a biblioteca falhe, retorna um placeholder simples
        $qrCodeSvg = QrCode::format('svg')->size(150)->margin(1)->generate($dossierUrl);

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
