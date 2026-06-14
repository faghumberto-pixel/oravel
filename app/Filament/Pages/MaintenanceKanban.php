<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceStatusHistory;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

class MaintenanceKanban extends Page
{
    protected static ?string $title = 'Quadro de Gestão Kanban';
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?string $navigationLabel = 'Quadro de Pátio (Kanban)';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.maintenance-kanban';

    public $viewMode = 'oficina'; 
    public $search = ''; 

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getTotalOrdersCount(): int
    {
        $tenant = \App\Support\Tenancy::current();
        if (!$tenant) return 0;

        $query = MaintenanceOrder::where('tenant_id', $tenant->id);
        
        if ($this->viewMode === 'oficina') {
            $query->whereNotIn('status', ['Concluída', 'Cancelada']);
        }
        
        return $query->count();
    }

    // MÉTODO FALTANTE ADICIONADO
    public function getAverageLeadTime(): float
    {
        $records = $this->getRecords()->flatten();
        if ($records->isEmpty()) return 0;

        $totalDays = $records->sum(fn($order) => $this->getDaysInStage($order));
        return round($totalDays / $records->count(), 1);
    }

    public function getStatuses(): array
    {
        return $this->viewMode === 'comercial' 
            ? [
                'disponivel' => ['title' => 'Disponível no Pátio', 'color' => 'bg-emerald-600'],
                'locado'     => ['title' => 'Em Obra (Locado)', 'color' => 'bg-blue-600'],
                'oficina'    => ['title' => 'Retido / Oficina', 'color' => 'bg-red-600'],
            ]
            : [
                'aguardando_diagnostico' => ['title' => 'Aguardando Diagnóstico', 'color' => 'bg-red-600'],
                'em_manutencao'          => ['title' => 'Em Manutenção', 'color' => 'bg-amber-500'],
                'aguardando_peca'        => ['title' => 'Aguardando Peça', 'color' => 'bg-cyan-600'],
                'teste_qualidade'        => ['title' => 'Teste de Qualidade', 'color' => 'bg-indigo-600'],
            ];
    }

    public function getRecords(): Collection
    {
        $tenant = \App\Support\Tenancy::current();
        if (!$tenant) return collect();

        $query = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->with(['asset', 'technician', 'parts', 'statusHistories']); 

        if ($this->viewMode === 'oficina') {
            $query->whereNotIn('status', ['Concluída', 'Cancelada']);
        }

        if (!empty(trim($this->search))) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('os_number', 'ilike', $term)
                         ->orWhere('description', 'ilike', $term)
                         ->orWhereHas('asset', fn($q) => $q->where('name', 'ilike', $term));
            });
        }

        $validStatuses = array_keys($this->getStatuses());

        return $query->get()->groupBy(function ($order) use ($validStatuses) {
            $status = ($this->viewMode === 'oficina') ? $order->internal_status : $order->commercial_status;
            return in_array($status, $validStatuses, true) ? $status : ($this->viewMode === 'oficina' ? 'aguardando_diagnostico' : 'oficina');
        });
    }

    public function getDaysInStage($record): int
    {
        $status = ($this->viewMode === 'oficina') ? $record->internal_status : ($record->commercial_status ?? 'oficina');
        
        $lastChange = $record->statusHistories()
            ->where('new_status', $status)
            ->latest('created_at')
            ->first();

        return $lastChange ? $lastChange->created_at->diffInDays(now()) : 0;
    }

    public function updateStatus($recordId, $newStatus)
    {
        $order = MaintenanceOrder::find($recordId);
        if ($order) {
            MaintenanceStatusHistory::create([
                'tenant_id'            => \App\Support\Tenancy::current()->id,
                'maintenance_order_id' => $recordId,
                'old_status'           => ($this->viewMode === 'oficina') ? $order->internal_status : $order->commercial_status,
                'new_status'           => $newStatus,
                'created_at'           => now(),
            ]);

            if ($this->viewMode === 'oficina') {
                $order->update(['internal_status' => $newStatus]);
            } else {
                $order->update(['commercial_status' => $newStatus]);
            }
            
            $this->dispatch('notify', ['message' => 'Status atualizado com sucesso!', 'type' => 'success']);
        }
    }
}