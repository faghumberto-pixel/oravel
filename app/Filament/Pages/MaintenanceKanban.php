<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MaintenanceKanban extends Page
{
    // Força o título correto em português no topo do sistema e na aba do navegador
    protected static ?string $title = 'Quadro de Gestão Kanban';

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?string $navigationLabel = 'Quadro de Pátio (Kanban)';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.maintenance-kanban';

    public function getStatuses(): array
    {
        // Definindo a paleta de cores correta e equilibrada por grau de atenção
        return [
            'aguardando_diagnostico' => [
                'title' => 'Aguardando Diagnóstico', 
                'color' => 'bg-red-600', // Vermelho Escuro (Crítico)
                'text'  => 'text-white'
            ],
            'em_manutencao' => [
                'title' => 'Em Manutenção', 
                'color' => 'bg-amber-500', // Laranja/Âmbar (Execução)
                'text'  => 'text-white'
            ],
            'aguardando_peca' => [
                'title' => 'Aguardando Peça', 
                'color' => 'bg-cyan-600', // Azul Petróleo / Oceano (Logística de Peças)
                'text'  => 'text-white'
            ],
            'teste_qualidade' => [
                'title' => 'Teste de Qualidade', 
                'color' => 'bg-indigo-600', // Índigo/Roxo Fechado (Revisão Técnica)
                'text'  => 'text-white'
            ],
            'disponivel_comercial' => [
                'title' => 'Disponível Comercial', 
                'color' => 'bg-emerald-600', // Verde Floresta (Liberado)
                'text'  => 'text-white'
            ],
        ];
    }

    public function getRecords(): Collection
    {
        return MaintenanceOrder::whereNotIn('status', ['Concluída', 'Cancelada'])
            ->with(['asset', 'technician'])
            ->get()
            ->groupBy('internal_status');
    }

    public function updateStatus($recordId, $newStatus)
    {
        $order = MaintenanceOrder::find($recordId);
        if ($order) {
            $order->update(['internal_status' => $newStatus]);
            $this->dispatch('notify', ['message' => 'Status atualizado!', 'type' => 'success']);
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}