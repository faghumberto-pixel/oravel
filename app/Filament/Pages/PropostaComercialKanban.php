<?php

namespace App\Filament\Pages;

use App\Models\PropostaComercial;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

/**
 * Kanban do Comercial, por status da Proposta -- pedido do usuário
 * 28/09/2026: o aviso "equipamento já solicitado" (SolicitacaoLocacao
 * nascida em PropostaComercial::aprovar(), antes do cliente responder)
 * "pode ficar muito bem" aqui, num card, em vez de só na tela individual
 * da proposta.
 *
 * Diferente de MaintenanceKanban (drag-and-drop livre entre colunas), aqui
 * NÃO existe mudança de status por arrastar: as transições de status já
 * têm regra de negócio real (aprovar()/rejeitar()/aceitarPeloCliente(),
 * cada uma com validação própria) -- este board é só uma visão agrupada,
 * cada card abre a tela de ações reais (ViewPropostaComercial).
 */
class PropostaComercialKanban extends Page
{
    protected static ?string $title = 'Kanban Comercial';

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?string $navigationLabel = 'Kanban Comercial';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.proposta-comercial-kanban';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', PropostaComercial::class);
    }

    /** Envia o rascunho para revisão direto do cartão (o aviso vai para os responsáveis configurados) (mesma regra do botão na tela da proposta). */
    public function enviar(string $propostaId): void
    {
        $proposta = PropostaComercial::where('tenant_id', Tenancy::current()?->id)->findOrFail($propostaId);
        abort_unless(auth()->user()?->can('update', $proposta), 403);

        try {
            $proposta->enviarParaComercial();
            Notification::make()->title('Proposta enviada para revisão')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();
        }
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * @return array<string, string>
     */
    public function getColumns(): array
    {
        return PropostaComercial::statusLabels();
    }

    /**
     * @return Collection<string, Collection<int, PropostaComercial>>
     */
    public function getRecords(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $propostas = PropostaComercial::where('tenant_id', $tenant->id)
            ->with(['client', 'sellerUser', 'solicitacaoLocacao'])
            ->latest('created_at')
            ->get();

        return $propostas->groupBy('status');
    }
}
