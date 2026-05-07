<?php

namespace App\Services;

use App\Models\{Asset, MaintenanceOrder, Attachment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MaintenanceService
{
    /**
     * Fluxo Real: Registra o Check-out com fotos, GPS e endereço auditável.
     * * @param array $dados - Dados básicos (asset_id, obs, etc)
     * @param array $evidences - Array contendo os arquivos e metadados (lat, lng, type)
     */
    public function registrarCheckOut(array $dados, array $evidences)
    {
        return DB::transaction(function () use ($dados, $evidences) {
            
            // 1. Criar a Ordem de Serviço (Check-out)
            $os = MaintenanceOrder::create([
                'os_number' => 'CHK-OUT-' . strtoupper(bin2hex(random_bytes(4))),
                'asset_id' => $dados['asset_id'],
                'maintenance_type' => 'checkout',
                'status' => 'Concluída',
                'tenant_id' => Auth::user()->tenant_id,
                'description' => $dados['obs'] ?? 'Check-out realizado via Oravel Mobile',
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            // 2. Atualizar Status do Ativo para 'in_use'
            $asset = Asset::findOrFail($dados['asset_id']);
            $asset->update(['status' => 'in_use']);

            // 3. Processar Evidências (Fotos + GPS)
            foreach ($evidences as $item) {
                // $item esperado: ['file' => UploadedFile, 'type' => 'painel', 'lat' => -22.9, 'lng' => -47.0]
                
                $path = $item['file']->store('evidences/' . $os->id, 'public');

                Attachment::create([
                    'maintenance_order_id' => $os->id,
                    'tenant_id' => Auth::user()->tenant_id,
                    'file_path' => $path,
                    'evidence_type' => $item['type'],
                    'latitude' => $item['lat'],
                    'longitude' => $item['lng'],
                    'captured_at' => now(), 
                ]);
            }

            Log::info("Check-out realizado para Ativo {$asset->name}, OS: {$os->os_number}");
            
            return $os;
        });
    }
}