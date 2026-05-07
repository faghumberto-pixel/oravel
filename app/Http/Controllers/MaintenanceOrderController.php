<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
// --- IMPORTAÇÕES ADICIONADAS PARA DEMO ---
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MaintenanceOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Lógica real da listagem (SaaS)
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Lógica real da view de criação (SaaS)
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Lógica real de salvamento (SaaS)
    }

    /**
     * Display the specified resource.
     * (Método padrão, pode ser usado para renderizar a view SaaS genérica)
     */
    public function show(MaintenanceOrder $maintenanceOrder)
    {
        // Lógica real da view de exibição genérica (SaaS)
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaintenanceOrder $maintenanceOrder)
    {
        // Lógica real da view de edição (SaaS)
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaintenanceOrder $maintenanceOrder)
    {
        // Lógica real de atualização (SaaS)
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaintenanceOrder $maintenanceOrder)
    {
        // Lógica real de exclusão (SaaS)
    }

    // --- MÉTODOS ADICIONADOS PARA A DEMONSTRAÇÃO (DEMO-READY) ---

    /**
     * Método técnico (simulando PWA): Recebe e grava evidência fotográfica auditável.
     * Vinculado à rota POST /maintenance-orders/{order}/evidence (Parte 1.B - Preparação Técnica)
     */
    public function addEvidence(Request $request, MaintenanceOrder $order)
    {
        // 1. Validação focada nos dados auditáveis (crítico para Pactual/JR Diesel)
        $request->validate([
            'image' => 'required|image|max:10240', // Max 10MB
            'evidence_type' => 'required|string',   // ex: checkout_avaria, preventiva_filtro
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            // O front PWA deve enviar a data/hora exata da captura (não do envio)
            'captured_at_front' => 'required|date_format:Y-m-d H:i:s', 
        ]);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');

            // 2. Definir caminho organizado: attachments/os_{UUID}/tipo/nome_unico.jpg
            $folder = "attachments/os_{$order->id}/{$request->evidence_type}";
            
            // Grava no disco 'public' (configurado em config/filesystems.php). 
            // Para demo local, lembre de rodar php artisan storage:link
            $path = $file->store($folder, 'public'); 

            // 3. Criar o registro de Evidência (Attachment)
            // Como usamos a relação evidences(), o tenant_id (BelongsToTenant)
            // é preenchido automaticamente se a OS já estiver contextualizada.
            $evidence = $order->evidences()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'evidence_type' => $request->evidence_type,
                // Dados Anti-Fraude (GPS precisão decimal garantida pelo cast no model)
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'captured_at' => Carbon::parse($request->captured_at_front),
            ]);

            return response()->json([
                'message' => 'Evidência de campo registrada com sucesso!',
                'evidence' => $evidence // O JSON usará os accessors novos automaticamente na demo
            ], 201);
        }

        return response()->json(['error' => 'Falha no upload da imagem.'], 400);
    }

    /**
     * Método Visual (Demo): Exibe o dashboard de demonstração da OS com o laudo fotográfico.
     * Vinculado à rota GET /gestao-locacao/os/{order} (Parte 1.B - Preparação da View)
     */
    public function showDashboardDemo(MaintenanceOrder $order)
    {
        // --- OTIMIZAÇÃO PARA DEMO: Eager Loading ---
        // Carregamos a OS e já trazemos as evidências vinculadas (evidences),
        // ordenadas pela data de captura (configurado no model).
        // Isso evita consultas 'n+1' na view Blade.
        $orderWithEvidences = $order->load([
            'evidences', 
            'asset',     // Carregamos também Asset/Client/Technician para o cabeçalho
            'client', 
            'technician'
        ]);

        // Retorna a view Blade específica da demonstração (re-resources/views/gestao-locacao/dashboard.blade.php)
        // passando a OS contextualizada e preenchida com as evidências auditáveis.
        return view('gestao-locacao.dashboard', [
            'order' => $orderWithEvidences
        ]);
    }
}