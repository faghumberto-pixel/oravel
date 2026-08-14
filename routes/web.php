<?php

use App\Http\Controllers\AIAnalysisPdfController;
use App\Http\Controllers\AssetDossierPdfController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ChatHistoryPdfController;
use App\Http\Controllers\EquipmentDamageReportController;
use App\Http\Controllers\HourMeterOfflineController;
use App\Http\Controllers\HourMeterPublicController;
use App\Http\Controllers\MaintenanceKanbanPrintController;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenanceOrderDossieController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\PrintQrController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteApprovalController;
use App\Http\Controllers\QuoteReportController;
use App\Http\Controllers\RentalDemoController;
use App\Http\Controllers\TablePrintController;
use App\Livewire\AssetDossierMobile;
use App\Livewire\EquipmentDamageMobile;
use App\Livewire\EquipmentMovementMobile;
use App\Livewire\EquipmentPatioArrivalMobile;
use App\Livewire\MaintenanceChecklistMobile;
use App\Livewire\MaintenanceOrderFieldWizard;
use App\Livewire\PreventiveMaintenanceMobile;
use App\Livewire\RentalDispatchChecklistMobile;
use App\Models\Asset;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\PreventiveMaintenanceExecution;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::redirect('/admin/innova/categories', '/admin/innova/bill-categories');
Route::get('/', fn () => redirect()->to('/admin'));

// Publica, sem auth de proposito -- e' a portaria/guarita escaneando o QR
// no celular dela, nao necessariamente logada no sistema.
Route::get('/portaria/verificar/{token}', function (string $token) {
    $movement = EquipmentMovement::where('qr_token', $token)->first();

    $liberado = $movement && $movement->status === EquipmentMovement::STATUS_CONCLUIDO;

    return view('portaria.verificar', [
        'movement' => $movement,
        'liberado' => $liberado,
    ]);
})->name('portaria.verificar');

// QR fixo por ativo (nao por movimentacao) -- cola no equipamento, escaneia
// a qualquer momento no patio pra ver status atual. Publica de proposito,
// mesmo motivo do portaria.verificar acima.
Route::get('/patio/ativo/{asset}', function (Asset $asset) {
    return view('portaria.asset-status', ['asset' => $asset]);
})->name('patio.ativo-status');

Route::get('/patio/ativo/{asset}/qr.svg', [PrintQrController::class, 'show'])
    ->name('assets.qr');

// Publica, sem auth de proposito -- e' o cliente final aprovando/reprovando
// o orçamento pelo link que recebeu por e-mail, sem conta no sistema.
Route::get('/orcamento/{token}', [QuoteApprovalController::class, 'show'])
    ->name('quotes.public-approval');
Route::post('/orcamento/{token}/aprovar', [QuoteApprovalController::class, 'approve'])
    ->name('quotes.public-approve');
Route::post('/orcamento/{token}/reprovar', [QuoteApprovalController::class, 'reject'])
    ->name('quotes.public-reject');

// Publica, sem auth de proposito -- funcionario do cliente que alugou o
// equipamento registra o horimetro sem precisar de conta no ERP. Token
// dedicado por ativo (Asset::hourMeterPublicToken()), so valido enquanto o
// ativo estiver "locado" -- ver HourMeterPublicController.
Route::get('/hour-meter/publico/{token}', [HourMeterPublicController::class, 'show'])
    ->name('hour-meter.public.show');
Route::post('/hour-meter/publico/{token}', [HourMeterPublicController::class, 'store'])
    ->name('hour-meter.public.store');

// 'verified' removido de proposito -- nenhum outro lugar do app (nenhum
// painel Filament) exige email verificado pra logar/usar, e nao existe
// fluxo real de envio/confirmacao de verificacao pros usuarios criados
// via TenantProvisioner/admin. Sobra do scaffolding padrao do Laravel:
// travava ate' o proprio super admin (humberto@oravel.com.br, sem
// email_verified_at em PROD) fora do relatorio, "erro pedindo verificar
// email" -- reportado pelo usuario, achado em PROD.
Route::get('/admin/app/maintenance-report', [MaintenanceReportController::class, 'show'])
    ->name('maintenance.report')
    ->middleware(['auth']);

Route::get('/admin/app/maintenance-kanban/print', [MaintenanceKanbanPrintController::class, 'show'])
    ->name('maintenance.kanban.print')
    ->middleware(['auth']);

Route::middleware(['auth'])->group(function () {
    Route::get('/admin/trocar-senha', fn () => view('auth.trocar-senha'))->name('admin.trocar-senha');
    Route::post('/admin/trocar-senha', [PasswordController::class, 'update'])->name('admin.trocar-senha.update');

    Route::get('/dashboard', function () {
        $user = auth()->user();

        // Técnicos vão direto para "Minhas Ordens de Serviço"
        if (! $user->isAdmin() && empty($user->supervisedDepartmentIds())) {
            return redirect()->route('filament.admin.pages.technician-daily-tasks');
        }

        $tenantSlug = $user->latest_tenant_slug ?? collect(Filament::getUserTenants($user))->first()?->slug ?? $user->tenant?->slug ?? $user->tenant_id;

        return $tenantSlug ? redirect()->route('filament.admin.pages.painel-gestao', ['tenant' => $tenantSlug]) : redirect()->to('/admin');
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

    // Agenda técnica mobile: próximos 30 dias de compromissos e O.S. agendadas
    Route::get('/admin/agenda-tecnico/mobile', 'App\Livewire\AgendaTecnicoMobile')
        ->name('agenda-tecnico.mobile');

    // "Modo Campo": execucao da O.S. no celular do tecnico, uma etapa por tela,
    // em vez do form de 7 abas do painel. Ponto de entrada em
    // EditMaintenanceOrder, na tabela de O.S. e no dossie mobile do ativo
    // (destino do QR da etiqueta).
    Route::get('/admin/maintenance-orders/{maintenanceOrder}/campo', MaintenanceOrderFieldWizard::class)
        ->name('maintenance-orders.field-wizard');

    // Wizard de Mobilização: técnico remove equipamento do pátio para o cliente
    Route::get('/admin/asset-movements/mobilization/{movement?}', 'App\Livewire\AssetMobilizationWizard')
        ->name('asset-movements.mobilization');

    // Wizard de Desmobilização: técnico retorna equipamento do cliente ao pátio
    Route::get('/admin/asset-movements/demobilization/{movement?}', 'App\Livewire\AssetDemobilizationWizard')
        ->name('asset-movements.demobilization');

    Route::get('/admin/maintenance-orders/{maintenanceOrder}/movimentacao/{type}', EquipmentMovementMobile::class)
        ->where('type', 'mobilizacao|desmobilizacao')
        ->name('maintenance-orders.equipment-movement-mobile');

    Route::get('/admin/equipment-movements/{equipmentMovement}/laudo-recebimento', EquipmentPatioArrivalMobile::class)
        ->name('equipment-movements.patio-arrival-mobile');

    Route::get('/admin/solicitacoes-locacao/{solicitacaoLocacao}/despacho', RentalDispatchChecklistMobile::class)
        ->name('solicitacoes-locacao.despacho-mobile');

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

    Route::get('/admin/ai-analyses/{record}/pdf', [AIAnalysisPdfController::class, 'download'])
        ->name('ai-analyses.pdf');

    Route::get('/admin/quotes/{record}/pdf', [QuoteReportController::class, 'download'])
        ->name('quotes.pdf');

    Route::get('/admin/assets/{asset}/dossie-pdf', [AssetDossierPdfController::class, 'download'])
        ->name('assets.dossier.pdf');

    // Versao pro campo/patio (celular do tecnico) do Dossie Rapido -- destino
    // do QR code do ativo, ver AssetResource::qr_code_display.
    Route::get('/admin/assets/dossie-mobile/{assetId?}', AssetDossierMobile::class)
        ->name('assets.dossier.mobile');

    // Tela dedicada de registro de horimetro, offline-first (JS puro,
    // localStorage + fila de sync -- ver HourMeterOfflineController).
    Route::get('/admin/hour-meter', [HourMeterOfflineController::class, 'show'])
        ->name('hour-meter.offline');

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
require __DIR__.'/chat.php';
require __DIR__.'/checkout.php';
