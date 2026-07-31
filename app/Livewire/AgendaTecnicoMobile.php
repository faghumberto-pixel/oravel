<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.checklist-mobile')]
class AgendaTecnicoMobile extends Component
{
    #[Url]
    public string $filterType = ''; // 'appointment' | 'order' | ''

    #[Url]
    public string $sortBy = 'date'; // 'date' | 'urgency'

    #[Url]
    public string $search = '';

    public function getAgendaItemsProperty(): Collection
    {
        $tenant = Tenancy::current();
        $user = Auth::user();

        if (!$tenant) {
            return collect();
        }

        $startDate = now()->startOfDay();
        $endDate = now()->addDays(30)->endOfDay();

        $items = collect();

        // Compromissos (Appointment)
        $appointments = Appointment::where('tenant_id', $tenant->id)
            ->where('technician_id', $user->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('completed', false)
            ->get()
            ->map(fn (Appointment $apt) => [
                'id' => $apt->id,
                'type' => 'appointment',
                'label' => 'AGENDAMENTO',
                'title' => $apt->assunto,
                'description' => $apt->descricao,
                'scheduled_at' => $apt->scheduled_at,
                'date' => $apt->scheduled_at->format('d/m'),
                'time' => $apt->scheduled_at->format('H:i'),
                'urgente' => $apt->urgente,
                'color' => $apt->urgente ? 'bg-red-900/30 text-red-400' : 'bg-blue-900/30 text-blue-400',
                'icon' => $apt->urgente ? '🚨' : '📅',
            ]);

        // Ordens de Serviço agendadas
        $orders = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('technician_id', $user->id)
            ->whereNotNull('scheduled_at')
            ->whereNotIn('status', ['Concluída', 'Cancelada'])
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->with(['asset', 'client'])
            ->get()
            ->map(fn (MaintenanceOrder $order) => [
                'id' => $order->id,
                'type' => 'order',
                'label' => 'ORDEM DE SERVIÇO',
                'title' => 'OS #' . ($order->os_number ?? 'S/N') . ' — ' . ($order->asset?->name ?? 'Equipamento'),
                'patrimonio' => $order->asset?->patrimonio ?? '—',
                'asset_name' => $order->asset?->name ?? 'Sem ativo',
                'client' => $order->client?->name ?? null,
                'description' => $order->description,
                'scheduled_at' => $order->scheduled_at,
                'date' => $order->scheduled_at->format('d/m'),
                'time' => $order->scheduled_at->format('H:i'),
                'status' => $order->status,
                'maintenance_type' => $order->maintenance_type,
                'color' => $this->getOrderStatusColor($order->status),
                'icon' => '🔧',
                'url' => route('maintenance-orders.field-wizard', $order),
            ]);

        $items = $appointments->concat($orders);

        // Aplicar filtros
        if ($this->filterType === 'appointment') {
            $items = $items->filter(fn ($item) => $item['type'] === 'appointment');
        } elseif ($this->filterType === 'order') {
            $items = $items->filter(fn ($item) => $item['type'] === 'order');
        }

        if ($this->search) {
            $search = strtolower($this->search);
            $items = $items->filter(fn ($item) =>
                str_contains(strtolower($item['title']), $search) ||
                str_contains(strtolower($item['description'] ?? ''), $search)
            );
        }

        // Aplicar ordenação
        $items = match ($this->sortBy) {
            'urgency' => $items->sortByDesc(fn ($i) => $i['urgente'] ?? false)->sortBy('scheduled_at'),
            'date' => $items->sortBy('scheduled_at'),
            default => $items->sortBy('scheduled_at'),
        };

        return $items;
    }

    private function getOrderStatusColor(string $status): string
    {
        return match ($status) {
            'Aberto', 'agendada' => 'bg-blue-900/30 text-blue-400',
            'Em Andamento' => 'bg-yellow-900/30 text-yellow-400',
            'Concluída' => 'bg-green-900/30 text-green-400',
            'Pendente' => 'bg-orange-900/30 text-orange-400',
            'Cancelada' => 'bg-red-900/30 text-red-400',
            default => 'bg-zinc-800 text-zinc-400',
        };
    }

    public function render()
    {
        return view('livewire.agenda-tecnico-mobile');
    }
}
