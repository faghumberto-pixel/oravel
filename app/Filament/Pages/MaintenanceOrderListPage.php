<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class MaintenanceOrderListPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-list';
    protected static ?string $navigationLabel = 'Ordens de Serviço';
    protected static ?string $navigationGroup = 'OS';
    protected static string $view = 'filament.admin.pages.maintenance-order-list';
    protected static ?string $slug = 'maintenance-order-list';

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Computed]
    public function orders(): Collection
    {
        $query = MaintenanceOrder::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('os_number', 'ilike', '%' . $this->search . '%')
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'ilike', '%' . $this->search . '%'))
                    ->orWhereHas('client', fn ($q) => $q->where('document', 'ilike', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    #[Computed]
    public function total(): int
    {
        return MaintenanceOrder::count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return MaintenanceOrder::where('status', 'Pendente')->count();
    }

    #[Computed]
    public function inProgressCount(): int
    {
        return MaintenanceOrder::where('status', 'Em Andamento')->count();
    }

    #[Computed]
    public function completedCount(): int
    {
        return MaintenanceOrder::where('status', 'Concluída')->count();
    }
}
