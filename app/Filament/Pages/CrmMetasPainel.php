<?php

namespace App\Filament\Pages;

use App\Models\CrmMeta;
use App\Services\CrmMetasService;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/** Painel de metas do mês: vendedor vê o próprio desempenho; quem vê tudo, a empresa e o time. */
class CrmMetasPainel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Painel de Metas';

    protected static ?string $title = 'Painel de Metas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.pages.crm-metas-painel';

    /** Mês no formato AAAA-MM. */
    public string $mes = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmMeta::class);
    }

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    private function mesCarbon(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $this->mes.'-01')->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    public function veTudo(): bool
    {
        return (bool) auth()->user()?->canSeeAllCrmLeads();
    }

    private function tenantId(): string
    {
        return (string) (Tenancy::current()?->id ?? auth()->user()->tenant_id);
    }

    /** Indicadores do próprio usuário. */
    public function getMeusProperty(): array
    {
        return app(CrmMetasService::class)->indicadores($this->tenantId(), auth()->id(), $this->mesCarbon());
    }

    /** Indicadores da empresa (só quem vê tudo). */
    public function getEmpresaProperty(): ?array
    {
        return $this->veTudo() ? app(CrmMetasService::class)->indicadores($this->tenantId(), null, $this->mesCarbon()) : null;
    }

    /** Linhas da equipe, ordenadas por atingimento (só quem vê tudo). */
    public function getEquipeProperty(): array
    {
        return $this->veTudo() ? app(CrmMetasService::class)->equipe($this->tenantId(), $this->mesCarbon()) : [];
    }

    public function mesAnterior(): void
    {
        $this->mes = $this->mesCarbon()->subMonth()->format('Y-m');
    }

    public function mesSeguinte(): void
    {
        $this->mes = $this->mesCarbon()->addMonth()->format('Y-m');
    }
}
