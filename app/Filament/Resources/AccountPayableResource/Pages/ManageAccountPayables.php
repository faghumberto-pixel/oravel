<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageAccountPayables extends ManageRecords
{
    protected static string $resource = AccountPayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * 🚀 MOTOR DE GERENCIAMENTO DE FLUXO (ABAS SUPERIORES CORRIGIDO)
     * Faz checagem segura do Tenant para evitar o erro de "Call to a member function on null"
     */
    public function getTabs(): array
    {
        $tenantId = Tenancy::current()?->id;

        // Se por algum motivo o Tenant não estiver carregado na sessão ainda, renderiza abas limpas temporariamente
        if (! $tenantId) {
            return [
                'todos' => Tab::make('Todos os Lançamentos'),
                'pendentes' => Tab::make('Pendentes'),
                'pagos' => Tab::make('Pagos'),
                'atrasados' => Tab::make('Atrasados'),
            ];
        }

        return [
            'todos' => Tab::make('Todos os Lançamentos'),

            'pendentes' => Tab::make('Pendentes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pendente'))
                ->badge(AccountPayable::where('tenant_id', $tenantId)->where('status', 'pendente')->count())
                ->badgeColor('warning'),

            'pagos' => Tab::make('Pagos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pago'))
                ->badge(AccountPayable::where('tenant_id', $tenantId)->where('status', 'pago')->count())
                ->badgeColor('success'),

            'atrasados' => Tab::make('Atrasados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'atrasado'))
                ->badge(AccountPayable::where('tenant_id', $tenantId)->where('status', 'atrasado')->count())
                ->badgeColor('danger'),
        ];
    }
}
