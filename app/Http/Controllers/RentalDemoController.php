<?php
// app/Http/Controllers/RentalDemoController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// --- AJUSTE PARA DEMO LOCADORA: INÍCIO (SEM EXCLUSÃO) ---
use App\Models\MaintenanceOrder;
use App\Models\Attachment;
use Carbon\Carbon;
// --- AJUSTE PARA DEMO LOCADORA: FIM (SEM EXCLUSÃO) ---

class RentalDemoController extends Controller
{
    // ... métodos index, checkout, checkin originais INTACTOS ...

    // --- CORREÇÃO CIRÚRGICA: INÍCIO (Issue #RouteNotFound na Demo) ---
    
    /**
     * Tela PHP Minimalista para Visualização/Impressão do Laudo.
     * (Puramente aditivo, focado na demo presencial).
     */
    public function laudoMinimalista(MaintenanceOrder $order)
    {
        // 1. Lógica de Blindagem Operacional DUMMY para Demo Prática
        // Como o sistema real B2B não permite aventuras no banco, 
        // nós reutilizamos a lógica DUMMY que já validamos visualmente na view industrializada,
        // gerando o dossiê OPERACIONAL AUDITÁVEL em memória para a demo presencial.
        if (!$order->relationLoaded('evidences')) {
            $capturedAt = now()->subHours(2);
            $lat = -22.906412; // Coordenadas GPS reais de Campinas (próximo à Pactual)
            $long = -47.061623;
            
            // Criamos as evidências operacionais DUMMY (com GPS e Data reais)
            $evidences = collect([
                new Attachment([
                    'evidence_type' => 'checkout_painel',
                    'file_path' => 'attachments/demo/painel_foto.jpg', // Caminho fake no storage
                    'latitude' => $lat,
                    'longitude' => $long,
                    'captured_at' => $capturedAt,
                ]),
                new Attachment([
                    'evidence_type' => 'checkout_avaria_esteira',
                    'file_path' => 'attachments/demo/esteira_foto.jpg', // Caminho fake no storage
                    'latitude' => $lat + 0.0001,
                    'longitude' => $long + 0.0001,
                    'captured_at' => $capturedAt->addMinutes(5),
                ])
            ]);
            
            // Atribui a relação de evidências DUMMY em memória
            $order->setRelation('evidences', $evidences);
        }

        // 2. Retorna a view PHP minimalista simulada.
        // (Assumindo que você criou resources/views/rental_demo/laudo_minimalista.blade.php).
        return view('rental_demo.laudo_minimalista', compact('order'));
    }
    
    // --- CORREÇÃO CIRÚRGICA: FIM ---
}