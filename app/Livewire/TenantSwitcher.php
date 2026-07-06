<?php

namespace App\Livewire;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Troca rapida de "tenant atuante" pro super admin, fixa no topo do painel
 * (ver AdminPanelProvider::renderHook TOPBAR_END). Complementa a tela
 * dedicada (SelectActingTenant, em Configuracoes) -- essa aqui e pro caso
 * de precisar migrar entre tenants rapido (ex: cliente reportou erro),
 * sem sair da tela atual.
 */
class TenantSwitcher extends Component
{
    public ?string $actingTenantId = null;

    public function mount(): void
    {
        $this->actingTenantId = session('acting_tenant_id');
    }

    #[On('acting-tenant-changed')]
    public function refreshSelection(): void
    {
        $this->actingTenantId = session('acting_tenant_id');
    }

    public function updatedActingTenantId(?string $value): void
    {
        if ($value) {
            session(['acting_tenant_id' => $value]);
        } else {
            session()->forget('acting_tenant_id');
        }

        // url()->current() aqui dentro resolveria pra URL da propria
        // chamada AJAX do Livewire (/livewire/update), nao pra tela que o
        // usuario esta vendo -- por isso recarrega via JS no navegador em
        // vez de tentar redirecionar no servidor.
        $this->js('window.location.reload()');
    }

    public function getTenantsProperty(): Collection
    {
        return Tenant::orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.tenant-switcher', [
            'tenants' => auth()->user()?->isSuperAdmin() ? $this->tenants : collect(),
        ]);
    }
}
