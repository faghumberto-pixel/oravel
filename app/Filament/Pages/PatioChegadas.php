<?php

namespace App\Filament\Pages;

use App\Models\EquipmentMovement;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Fila de desmobilizacoes aguardando o Laudo de Recebimento (checklist
 * estruturado da chegada fisica no patio, ver EquipmentPatioArrivalMobile
 * -- mesmo padrao do checklist de desmobilizacao, so' que pra chegada).
 * Antes desta tela virar um checklist de verdade, era so um botao +
 * campo de texto livre; o registro/liberacao do ativo ficava tudo no
 * mesmo passo. Agora o EquipmentPatioArrival nasce em rascunho assim que
 * o Laudo comeca (ver mount() do componente mobile), e so' fica
 * "concluido" (completed_at preenchido) quando o checklist chega a 100%
 * -- entao a fila aqui precisa continuar mostrando quem tem um laudo em
 * andamento, nao so' quem nunca comecou nenhum.
 */
class PatioChegadas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Chegadas no Pátio';

    protected static ?string $title = 'Chegadas no Pátio';

    protected static ?string $navigationGroup = 'Logística';

    protected static string $view = 'filament.pages.patio-chegadas';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquipmentMovement::class);
    }

    public function getPendingProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        return EquipmentMovement::where('tenant_id', $tenant->id)
            ->where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)
            ->where('status', EquipmentMovement::STATUS_CONCLUIDO)
            ->where(function ($query) {
                $query->whereDoesntHave('patioArrival')
                    ->orWhereHas('patioArrival', fn ($q) => $q->whereNull('completed_at'));
            })
            ->with(['asset', 'maintenanceOrder.client', 'patioArrival.items'])
            ->latest('completed_at')
            ->get();
    }
}
