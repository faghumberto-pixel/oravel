<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Filament\Facades\Filament;
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

    /**
     * 🔒 CONTROLE DE VISIBILIDADE DO MENU (SIDEBAR)
     * Esconde completamente o link do menu lateral se o técnico logado
     * não possuir a permissão explícita no banco de dados.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        
        if (! $user) {
            return false;
        }

        // Se for o administrador master do Oravel, exibe sempre
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Valida diretamente contra o Toggle dinâmico do Spatie no banco
        return $user->hasPermissionTo('ler_ordem_servico', 'web');
    }

    /**
     * 🔒 PROTEÇÃO DE ACESSO DIRETO POR URL
     * Se o usuário tentar forçar a URL da página no navegador sem permissão,
     * o sistema aborta a requisição antes de carregar qualquer dado do Kanban.
     */
    public function mount()
    {
        $user = auth()->user();
        
        if (! $user || (! (method_exists($user, 'isAdmin') && $user->isAdmin()) && !$user->hasPermissionTo('ler_ordem_servico', 'web'))) {
            abort(403, 'Acesso não autorizado ao Quadro de Gestão Kanban.');
        }
    }

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

    /**
     * 🔒 REGRA DE FILTRAGEM DO TÉCNICO E TENANT
     * Garante que o técnico veja somente as OS do seu próprio nome e da empresa logada.
     */
    public function getRecords(): Collection
    {
        $user = auth()->user();
        $tenantId = Filament::getTenant()?->id;

        if (!$tenantId) {
            return collect();
        }

        return MaintenanceOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['Concluída', 'Cancelada'])
            // Se NÃO for admin, aplica a restrição de técnico estrita
            ->when(!($user && method_exists($user, 'isAdmin') && $user->isAdmin()), function ($query) use ($user) {
                return $query->where('technician_id', $user->id);
            })
            ->with(['asset', 'technician'])
            ->get()
            ->groupBy('internal_status');
    }

    /**
     * 🔒 SEGURANÇA NA MOVIMENTAÇÃO DO CARD
     * Impede que um técnico intermedeie ou mude o status de uma OS que não pertença a ele por requisições maliciosas.
     */
    public function updateStatus($recordId, $newStatus)
    {
        $user = auth()->user();
        $tenantId = Filament::getTenant()?->id;

        $order = MaintenanceOrder::where('tenant_id', $tenantId)->find($recordId);

        if ($order) {
            // Validação de segurança: se não for admin, confere se a OS é realmente dele antes de salvar
            if (!($user && method_exists($user, 'isAdmin') && $user->isAdmin()) && $order->technician_id !== $user->id) {
                $this->dispatch('notify', ['message' => 'Operação não autorizada.', 'type' => 'danger']);
                return;
            }

            $order->update(['internal_status' => $newStatus]);
            $this->dispatch('notify', ['message' => 'Status atualizado!', 'type' => 'success']);
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}