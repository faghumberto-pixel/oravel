<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Pedido do usuário 2026-08-25: "sei quem da minha equipe está
 * sobrecarregado e quem está ocioso, sem depender de filtrar técnico por
 * técnico?". Investigação encontrou TechnicianOrderStats (autoatendido, só
 * mostra "minhas OS" pro técnico logado) e o filtro de technician_id em
 * MaintenanceOrderResource (exige clicar um de cada vez) -- nenhum dos
 * dois compara técnicos lado a lado, e não existia conceito de "técnico
 * ocioso" em lugar nenhum do sistema (só existe pra Ativos).
 *
 * "Técnico" aqui é qualquer User do tenant com technician_id preenchido em
 * alguma OS -- não existe role dedicada de "técnico" no sistema
 * (confirmado em MaintenanceOrderResource::form(), Select de technician_id
 * usa User::where('tenant_id', ...) sem filtro de role).
 *
 * Carga = OS em Aberto/Pendente/Em Andamento (mesmo critério de "em
 * aberto" já usado em TechnicianOrderStats). Ocioso = zero OS nesse
 * estado. A lista de técnicos considerados é quem já recebeu QUALQUER OS
 * (histórica, não só em aberto) -- isso deixa de fora um usuário recém
 * criado que nunca foi técnico de nada; um técnico sem OS EM ABERTO agora
 * mas com histórico aparece normalmente, marcado como ocioso.
 */
class CargaTecnica extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Carga de Técnicos';

    protected static ?string $title = 'Carga de Técnicos';

    protected static string $view = 'filament.pages.carga-tecnica';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    private const STATUS_EM_ABERTO = ['Aberto', 'Pendente', 'Em Andamento'];

    public function getCargaProperty(): Collection
    {
        $tenantId = Tenancy::current()?->id;

        if (! $tenantId) {
            return collect();
        }

        $technicianIds = MaintenanceOrder::query()
            ->whereNotNull('technician_id')
            ->distinct()
            ->pluck('technician_id');

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $technicianIds)
            ->withCount([
                'maintenanceOrders as em_aberto_count' => fn ($query) => $query->whereIn('status', self::STATUS_EM_ABERTO),
            ])
            ->get()
            ->map(fn (User $technician) => [
                'technician' => $technician,
                'em_aberto' => $technician->em_aberto_count,
                'ocioso' => $technician->em_aberto_count === 0,
                'url' => $this->technicianOpenOrdersUrl($technician->id),
            ])
            ->sortByDesc('em_aberto')
            ->values();
    }

    private function technicianOpenOrdersUrl(string $technicianId): string
    {
        return MaintenanceOrderResource::getUrl('index', [
            'tableFilters' => [
                'technician_id' => ['value' => $technicianId],
                'status' => ['values' => self::STATUS_EM_ABERTO],
            ],
        ]);
    }
}
