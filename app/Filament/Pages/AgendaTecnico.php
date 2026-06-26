<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Contract;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AgendaTecnico extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Programação';
    protected static ?string $title = 'Programação';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.agenda-tecnico';

    public string $weekStartDate = '';
    public string|int $selectedTechnician = '';
    public string $activeTab = 'semana';
    public string $filterAsset = '';
    public string $filterCategory = '';
    public string $filterTechnician = '';
    public string $filterContract = '';
    public bool $showAppointmentModal = false;
    public bool $showPendenciesModal = false;
    public string $appointmentDate = '';
    public string $appointmentTime = '';
    public string $appointmentSubject = '';
    public string $appointmentDescription = '';
    public bool $appointmentUrgent = false;

    public function mount(): void
    {
        $this->weekStartDate = now()->startOfWeek()->toDateString();
        $user = auth()->user();
        $this->selectedTechnician = $user ? (string)$user->id : '';
    }

    public function setWeekOffset(int $offset): void
    {
        $this->weekStartDate = now()->startOfWeek()->addWeeks($offset)->toDateString();
    }

    public function setSelectedTechnician(string|int $techId): void
    {
        $this->selectedTechnician = (string)$techId;
    }

    public function openAppointmentModal(string $date = '', string $time = ''): void
    {
        $this->appointmentDate = $date;
        $this->appointmentTime = $time;
        $this->showAppointmentModal = true;
    }

    public function openPendenciesModal(): void
    {
        $this->showPendenciesModal = true;
    }

    public function closePendenciesModal(): void
    {
        $this->showPendenciesModal = false;
    }

    public function saveAppointment(): void
    {
        $this->validate([
            'appointmentSubject' => 'required|string',
            'appointmentDate' => 'required|date',
            'appointmentTime' => 'required',
        ]);

        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        $techId = $isAdmin && $this->selectedTechnician ? (int)$this->selectedTechnician : $user->id;

        $scheduledAt = Carbon::parse($this->appointmentDate . ' ' . $this->appointmentTime);

        Appointment::create([
            'tenant_id' => $tenantId,
            'technician_id' => $techId,
            'assunto' => $this->appointmentSubject,
            'descricao' => $this->appointmentDescription,
            'urgente' => $this->appointmentUrgent,
            'completed' => false,
            'scheduled_at' => $scheduledAt,
        ]);

        $this->showAppointmentModal = false;
        $this->appointmentSubject = '';
        $this->appointmentDescription = '';
        $this->appointmentUrgent = false;
    }

    public function toggleAppointmentComplete(string $appointmentId): void
    {
        $appointment = Appointment::find($appointmentId);
        if ($appointment) {
            $appointment->update(['completed' => !$appointment->completed]);
        }
    }

    public function getPendingCountForTechnicianProperty(): int
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        if (!$tenantId) return 0;

        if ($isAdmin && $this->selectedTechnician) {
            return Appointment::where('tenant_id', $tenantId)
                ->where('technician_id', (int)$this->selectedTechnician)
                ->where('completed', false)
                ->count();
        }

        return Appointment::where('tenant_id', $tenantId)
            ->where('technician_id', $user->id)
            ->where('completed', false)
            ->count();
    }

    public function getPendingCountProperty(): int
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        if (!$tenantId) return 0;
        return Appointment::where('tenant_id', $tenantId)
            ->where('technician_id', $user->id)
            ->where('completed', false)
            ->count();
    }

    public function getCompletedCountProperty(): int
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        if (!$tenantId) return 0;
        return Appointment::where('tenant_id', $tenantId)
            ->where('technician_id', $user->id)
            ->where('completed', true)
            ->count();
    }

    public function getSelectedTechnicianAppointmentsProperty(): Collection
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        if (!$tenantId) return collect();

        if ($isAdmin && $this->selectedTechnician) {
            return Appointment::where('tenant_id', $tenantId)
                ->where('technician_id', (int)$this->selectedTechnician)
                ->orderBy('scheduled_at', 'desc')
                ->get();
        }

        return Appointment::where('tenant_id', $tenantId)
            ->where('technician_id', $user->id)
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }

    public function getSelectedPendingCountProperty(): int
    {
        return $this->selectedTechnicianAppointments->where('completed', false)->count();
    }

    public function getSelectedCompletedCountProperty(): int
    {
        return $this->selectedTechnicianAppointments->where('completed', true)->count();
    }

    public function getAllAppointmentsProperty(): Collection
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        if (!$tenantId) return collect();
        return Appointment::where('tenant_id', $tenantId)
            ->where('technician_id', $user->id)
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }

    public function getTechniciansProperty(): Collection
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        if (!$tenantId) return collect();
        
        if ($isAdmin) {
            return User::where('tenant_id', $tenantId)
                ->where('id', '!=', 1)
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get()
                ->map(function($u) use ($tenantId) {
                    $pendingCount = Appointment::where('tenant_id', $tenantId)
                        ->where('technician_id', $u->id)
                        ->where('completed', false)
                        ->count();
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'pending_count' => $pendingCount
                    ];
                });
        }
        return collect([$user])->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'pending_count' => 0]);
    }

    public function getWeekDaysProperty(): Collection
    {
        $startDate = Carbon::parse($this->weekStartDate ?: now()->startOfWeek()->toDateString());
        return collect(range(0, 6))->map(function ($day) use ($startDate) {
            $date = $startDate->clone()->addDays($day);
            return [
                'date' => $date->toDateString(),
                'day_name' => $date->format('D'),
                'day_number' => $date->format('d'),
                'month' => $date->format('m'),
                'full_date' => $date->format('d/m'),
                'is_today' => $date->isToday()
            ];
        });
    }

    public function getTimeSlots(): array
    {
        return ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
    }

    public function getGridEventsProperty(): array
    {
        $tenantId = \App\Support\Tenancy::current()?->id;
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        if (!$tenantId) return [];

        try {
            $startDate = Carbon::parse($this->weekStartDate ?: now()->startOfWeek()->toDateString());
        } catch (\Exception $e) {
            $startDate = now()->startOfWeek();
        }
        
        $endDate = $startDate->clone()->addDays(6)->endOfDay();

        if ($this->activeTab === 'hoje') {
            $startDate = now()->startOfDay();
            $endDate = now()->endOfDay();
        } elseif ($this->activeTab === 'mes') {
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
        }

        $grid = [];
        $counter = 0;

        // AGENDAMENTOS
        $appointmentQuery = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->with(['technician']);

        if ($isAdmin && $this->selectedTechnician) {
            $appointmentQuery->where('technician_id', (int)$this->selectedTechnician);
        } elseif (!$isAdmin) {
            $appointmentQuery->where('technician_id', $user->id);
        }

        $appointments = $appointmentQuery->get();

        foreach ($appointments as $appointment) {
            $scheduledDate = $appointment->scheduled_at->toDateString();
            $hour = $appointment->scheduled_at->format('H:00');
            $techId = $appointment->technician_id;

            if ($appointment->completed) {
                $statusColor = 'bg-green-600';
                $badge = '✅ FEITO';
            } elseif ($appointment->urgente) {
                $statusColor = 'bg-red-500';
                $badge = '🔴 URGENTE';
            } else {
                $statusColor = 'bg-purple-600';
                $badge = '📅 Normal';
            }

            $counter++;
            $key = "app_{$techId}_{$scheduledDate}_{$hour}_{$counter}";
            $grid[$key] = [
                'id' => $appointment->id,
                'technician_id' => $techId,
                'date' => $scheduledDate,
                'hour' => $hour,
                'title' => $appointment->assunto,
                'asset' => $badge,
                'status' => $appointment->completed ? 'completed' : ($appointment->urgente ? 'urgente' : 'normal'),
                'color' => $statusColor,
                'uri' => '#',
                'tech_name' => $appointment->technician?->name ?? 'Sem técnico',
                'type' => 'appointment',
                'appointmentId' => $appointment->id,
                'completed' => $appointment->completed,
            ];
        }

        // ORDENS DE SERVIÇO
        $orderQuery = MaintenanceOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->with(['asset', 'technician', 'asset.category', 'asset.contracts']);

        if ($isAdmin && $this->selectedTechnician) {
            $orderQuery->where('technician_id', (int)$this->selectedTechnician);
        } elseif (!$isAdmin) {
            $orderQuery->where('technician_id', $user->id);
        }

        if ($this->filterAsset) $orderQuery->where('asset_id', $this->filterAsset);
        if ($this->filterCategory) $orderQuery->whereHas('asset', fn($q) => $q->where('asset_category_id', $this->filterCategory));
        if ($this->filterContract) $orderQuery->whereHas('asset.contracts', fn($q) => $q->where('contracts.id', $this->filterContract));

        $orders = $orderQuery->get();

        foreach ($orders as $order) {
            try {
                if (!$order->scheduled_at) continue;
                
                $scheduledAt = is_string($order->scheduled_at) 
                    ? Carbon::parse($order->scheduled_at)
                    : $order->scheduled_at;
                
                if (!$scheduledAt instanceof Carbon) {
                    continue;
                }
                
                $scheduledDate = $scheduledAt->toDateString();
                $hour = $scheduledAt->format('H:00');
                $techId = $order->technician_id;
                
                // ✅ GARANTIR URI SEMPRE PREENCHIDA
                $orderUri = route('filament.admin.resources.maintenance-orders.edit', ['record' => $order->id]);
                
                $statusColor = match ($order->status) {
                    'agendada', 'open' => 'bg-blue-600',
                    'em_progresso', 'in_progress' => 'bg-yellow-500',
                    'concluida', 'completed' => 'bg-green-600',
                    'pendente', 'pending' => 'bg-orange-500',
                    'cancelada', 'cancelled' => 'bg-red-600',
                    default => 'bg-gray-600',
                };
                
                $counter++;
                $key = "os_{$techId}_{$scheduledDate}_{$hour}_{$counter}";
                $grid[$key] = [
                    'id' => $order->id,
                    'technician_id' => $techId,
                    'date' => $scheduledDate,
                    'hour' => $hour,
                    'title' => 'OS #' . ($order->os_number ?? 'N/A'),
                    'asset' => $order->asset?->name ?? 'Equipamento',
                    'status' => $order->status,
                    'color' => $statusColor,
                    'uri' => $orderUri,
                    'tech_name' => $order->technician?->name ?? 'Sem técnico',
                    'type' => 'order',
                ];
            } catch (\Exception $e) {
                \Log::warning("Erro ao processar order {$order->id}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        return $grid;
    }
}
