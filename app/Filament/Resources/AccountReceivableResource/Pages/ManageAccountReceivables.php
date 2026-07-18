<?php

namespace App\Filament\Resources\AccountReceivableResource\Pages;

use App\Filament\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageAccountReceivables extends ManageRecords
{
    protected static string $resource = AccountReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tenantId = Tenancy::current()?->id;

        if (! $tenantId) {
            return [
                'todos' => Tab::make('Todos os Lançamentos'),
                'pendentes' => Tab::make('Pendentes'),
                'pagos' => Tab::make('Recebidos'),
                'atrasados' => Tab::make('Atrasados'),
            ];
        }

        return [
            'todos' => Tab::make('Todos os Lançamentos'),

            'pendentes' => Tab::make('Pendentes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pendente'))
                ->badge(AccountReceivable::where('tenant_id', $tenantId)->where('status', 'pendente')->count())
                ->badgeColor('warning'),

            'pagos' => Tab::make('Recebidos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pago'))
                ->badge(AccountReceivable::where('tenant_id', $tenantId)->where('status', 'pago')->count())
                ->badgeColor('success'),

            'atrasados' => Tab::make('Atrasados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'atrasado'))
                ->badge(AccountReceivable::where('tenant_id', $tenantId)->where('status', 'atrasado')->count())
                ->badgeColor('danger'),
        ];
    }
}
