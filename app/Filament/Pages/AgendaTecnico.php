<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Collection;

class AgendaTecnico extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Minha Agenda';
    protected static ?string $title = 'Agenda de Atendimentos';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?int $navigationSort = 1;

    // Vincula à view padrão do Filament para páginas customizadas
    protected static string $view = 'filament.pages.agenda-tecnico';

    /**
     * 📊 HISTÓRICO E AGENDA:
     * Método computado que traz as ordens de serviço do técnico logado.
     * Administradores continuam vendo todo o pátio.
     */
    public function getEventsProperty(): Collection
    {
        $user = auth()->user();
        $tenantId = Filament::getTenant()?->id;

        if (!$tenantId) {
            return collect();
        }

        return MaintenanceOrder::query()
            ->where('tenant_id', $tenantId)
            ->when(!($user && method_exists($user, 'isAdmin') && $user->isAdmin()), function ($query) use ($user) {
                return $query->where('technician_id', $user->id);
            })
            ->with(['asset', 'client'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (MaintenanceOrder $order) {
                
                // 🔑 TRADUÇÃO E MAPEAMENTO DOS STATUS OPERACIONAIS DO ORAVEL
                $statusLabel = match ($order->status) {
                    'Aberto', 'open' => 'Em Aberto',
                    'Em Andamento', 'in_progress' => 'Em Andamento',
                    'Concluída', 'completed' => 'Concluída',
                    'Pendente', 'pending' => 'Pendente',
                    'Cancelada', 'cancelled' => 'Cancelada',
                    default => ucfirst($order->status ?? 'Aberto'),
                };

                // Paleta de estilos premium ajustada para bordas e fundos no PWA
                $color = match ($order->status) {
                    'Aberto', 'open' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/20', 
                    'Em Andamento', 'in_progress' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20', 
                    'Concluída', 'completed' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20', 
                    'Pendente', 'pending' => 'bg-gray-50 dark:bg-gray-500/10 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-500/20', 
                    default => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-500/20', 
                };

                // Garante que o número identificador nunca apareça vazio ou quebre a concatenação
                $osIdentifier = $order->os_number ?? 'N/A';

                return [
                    'id' => $order->id,
                    'title' => "Ordem de Serviço #" . $osIdentifier,
                    'asset' => $order->asset?->name ?? 'Equipamento sem identificação',
                    'client' => $order->client?->name ?? 'Atendimento Interno (Sem Cliente)',
                    'status_label' => $statusLabel,
                    'style' => $color,
                    'date' => $order->created_at ? $order->created_at->format('d/m/Y') : now()->format('d/m/Y'),
                    'url' => url("/admin/" . Filament::getTenant()->id . "/maintenance-orders/{$order->id}/edit"),
                ];
            });
    }
}