<?php

namespace App\Filament\Central\Pages;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Árvore Tenant -> Clientes (pedido do usuário 2026-09-27): visão rápida,
 * por tenant, de quantos clientes ele tem, quais têm contrato vigente
 * ("ativo" = existe Contract com status='Ativo' hoje -- confirmado com o
 * usuário, não é portal_access_enabled_at nem soft-delete), quantos Assets
 * cada cliente tem, e o login de portal daquele cliente (Client é o próprio
 * usuário autenticável do guard 'client' -- 1 login por empresa cliente,
 * não múltiplos usuários por cliente, confirmado com o usuário).
 */
class TenantTree extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Árvore de Tenants';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $title = 'Árvore de Tenants';

    protected static string $view = 'filament.central.pages.tenant-tree';

    /**
     * @return Collection<int, array{tenant: Tenant, clients: Collection}>
     */
    public function getTenantTree(): Collection
    {
        $assetCounts = Asset::withoutGlobalScopes()
            ->selectRaw('client_id, count(*) as total')
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->pluck('total', 'client_id');

        $activeClientIds = Contract::withoutGlobalScopes()
            ->where('status', 'Ativo')
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id')
            ->flip();

        return Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) use ($assetCounts, $activeClientIds) {
                $clients = Client::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get()
                    ->map(function (Client $client) use ($assetCounts, $activeClientIds) {
                        return [
                            'client' => $client,
                            'ativo' => $activeClientIds->has($client->id),
                            'equipamentos' => (int) ($assetCounts[$client->id] ?? 0),
                            'portal_habilitado' => filled($client->portal_access_enabled_at),
                        ];
                    });

                return [
                    'tenant' => $tenant,
                    'clients' => $clients,
                ];
            });
    }
}
