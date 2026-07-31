<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\SolicitacaoLocacao;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pedido explícito do usuário: hoje, quando o Comercial marca uma
 * Solicitação de Locação como "Reservar para Manutenção (Urgente)"
 * (status_comercial=reserva_manutencao), o único aviso pra Manutenção é
 * reativo -- uma faixa vermelha no card do Ativo dentro do Kanban do Pátio
 * (MaintenanceKanban::getUrgentAssetIds()) e um bloqueio ao tentar abrir
 * OS nova (MaintenanceOrderResource::assetTemUrgenciaLocacao()). Não existe
 * uma tela própria onde a Manutenção veja a fila inteira.
 *
 * Esta página é essa tela: lista toda SolicitacaoLocacao com
 * status_comercial=reserva_manutencao do tenant, uma linha por Ativo
 * envolvido (usa SolicitacaoLocacao::assetIds(), cobre tanto o campo legado
 * asset_id quanto combo/lote), com a OS aberta atual (se houver) e prazo.
 */
class ReservasUrgentes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationGroup = 'Manutenção';
    protected static ?string $navigationLabel = 'Reservas Urgentes';

    protected static ?string $title = 'Reservas Urgentes para Manutenção';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reservas-urgentes';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return null;
        }

        $count = SolicitacaoLocacao::where('tenant_id', $tenant->id)
            ->where('status_comercial', 'reserva_manutencao')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * Uma linha por (Solicitação, Ativo) -- solicitações "combo"/lote viram
     * várias linhas, uma por Ativo do combo. Solicitações ainda sem nenhum
     * Ativo definido (só categoria) viram 1 linha com asset=null, pra
     * Manutenção pelo menos saber que a reserva existe.
     *
     * @return Collection<int, array{solicitacao: SolicitacaoLocacao, asset: ?Asset, openOrder: ?MaintenanceOrder, diasRestantes: ?int, vencida: bool}>
     */
    public function getReservas(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $solicitacoes = SolicitacaoLocacao::where('tenant_id', $tenant->id)
            ->where('status_comercial', 'reserva_manutencao')
            ->with(['customer', 'category', 'asset', 'assets'])
            ->get();

        $rows = collect();

        foreach ($solicitacoes as $solicitacao) {
            $assetIds = $solicitacao->assetIds();

            if ($assetIds->isEmpty()) {
                $rows->push($this->buildRow($solicitacao, null));

                continue;
            }

            foreach ($assetIds as $assetId) {
                $rows->push($this->buildRow($solicitacao, Asset::find($assetId)));
            }
        }

        return $rows->sortBy(fn ($row) => $row['diasRestantes'] ?? PHP_INT_MAX)->values();
    }

    private function buildRow(SolicitacaoLocacao $solicitacao, ?Asset $asset): array
    {
        $openOrder = $asset
            ? MaintenanceOrder::where('asset_id', $asset->id)
                ->whereNotIn('status', ['Concluída', 'Cancelada', 'Completado'])
                ->latest()
                ->first()
            : null;

        $prazo = $solicitacao->data_saida_prevista;
        $diasRestantes = $prazo
            ? (int) now()->startOfDay()->diffInDays(Carbon::parse($prazo)->startOfDay(), false)
            : null;

        return [
            'solicitacao' => $solicitacao,
            'asset' => $asset,
            'openOrder' => $openOrder,
            'diasRestantes' => $diasRestantes,
            'vencida' => $diasRestantes !== null && $diasRestantes < 0,
        ];
    }

    public function getKpis(): array
    {
        $reservas = $this->getReservas();

        return [
            'total' => $reservas->count(),
            'semOs' => $reservas->filter(fn ($r) => $r['asset'] && ! $r['openOrder'])->count(),
            'vencidas' => $reservas->filter(fn ($r) => $r['vencida'])->count(),
            'prontas' => $reservas->filter(fn ($r) => $r['asset']?->status === Asset::STATUS_DISPONIVEL)->count(),
        ];
    }

    /**
     * A providência que faltava: até aqui a fila só avisava "sem OS
     * aberta" sem dar nenhum jeito de agir. Cria uma OS tipo "Reserva"
     * (não é trabalho de reparo, é o registro formal de bloqueio) já
     * vinculada à Solicitação (FK direta, não mais best-effort por
     * Ativo+janela de tempo) e muda o Ativo pra "reservado" -- ninguém
     * mais consegue selecioná-lo numa Solicitação nova enquanto isso.
     */
    public function abrirOsReserva(string $solicitacaoId, string $assetId): void
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return;
        }

        $solicitacao = SolicitacaoLocacao::where('tenant_id', $tenant->id)
            ->where('id', $solicitacaoId)
            ->where('status_comercial', 'reserva_manutencao')
            ->with('customer')
            ->first();

        if (! $solicitacao) {
            Notification::make()->title('Solicitação não encontrada ou não é mais urgente')->danger()->send();

            return;
        }

        $asset = Asset::where('tenant_id', $tenant->id)->find($assetId);

        if (! $asset) {
            Notification::make()->title('Ativo não encontrado')->danger()->send();

            return;
        }

        $jaTemOsAberta = MaintenanceOrder::where('asset_id', $asset->id)
            ->whereNotIn('status', ['Concluída', 'Cancelada', 'Completado'])
            ->exists();

        if ($jaTemOsAberta) {
            Notification::make()->title('Este ativo já tem uma OS aberta')->warning()->send();

            return;
        }

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'solicitacao_locacao_id' => $solicitacao->id,
            'maintenance_type' => MaintenanceOrder::TYPE_RESERVA,
            'status' => MaintenanceOrder::STATUS_RESERVADO,
            'description' => 'Reserva de equipamento para a Solicitação de Locação de '
                .($solicitacao->customer?->name ?? 'cliente não informado').'.',
        ]);

        $asset->update(['status' => Asset::STATUS_RESERVADO]);

        Notification::make()->title('OS de Reserva criada -- Ativo bloqueado')->success()->send();
    }

    /**
     * O outro lado do ciclo (discutido com o usuário): quando o Ativo já
     * está pronto, Manutenção conclui a OS de Reserva aqui e ele volta
     * pra "disponível" -- só a partir daí o Comercial consegue fechar o
     * contrato (SolicitacaoLocacao::booted() já exige Asset disponível
     * pra isso, então sem essa ação o fluxo ficaria travado). O outro
     * caminho de saída -- Comercial cancelar ou sair de reserva_manutencao
     * sem fechar -- é automático, ver SolicitacaoLocacaoObserver::revogarReservaAbandonada().
     */
    public function concluirReserva(string $maintenanceOrderId): void
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return;
        }

        $order = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('id', $maintenanceOrderId)
            ->where('maintenance_type', MaintenanceOrder::TYPE_RESERVA)
            ->with('asset')
            ->first();

        if (! $order) {
            Notification::make()->title('OS de Reserva não encontrada')->danger()->send();

            return;
        }

        $order->update(['status' => 'Concluída']);

        if ($order->asset && $order->asset->status === Asset::STATUS_RESERVADO) {
            $order->asset->update(['status' => Asset::STATUS_DISPONIVEL]);
        }

        Notification::make()->title('Ativo liberado -- pronto pra o Comercial fechar o contrato')->success()->send();
    }
}
