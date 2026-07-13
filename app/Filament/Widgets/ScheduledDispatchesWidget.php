<?php

namespace App\Filament\Widgets;

use App\Models\EquipmentMovement;
use App\Support\Tenancy;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Substitui "OS Abertas por Técnico" -- mostra o que está de fato
 * programado pra sair do pátio nos próximos dias (EquipmentMovement
 * agendado via Programação, ver AgendaTecnicoWidget), não só a contagem
 * de OS por técnico. Mesma regra de visibilidade por setor da Programação:
 * só quem supervisiona algum setor (ou admin) enxerga -- ver
 * User::supervisedDepartmentIds().
 */
class ScheduledDispatchesWidget extends BaseWidget
{
    protected static ?string $heading = 'Programados para Sair';

    protected int|string|array $columnSpan = ['md' => 2];

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) Tenancy::current() && $user && ($user->isAdmin() || ! empty($user->supervisedDepartmentIds()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EquipmentMovement::query()
                    ->where('type', EquipmentMovement::TYPE_MOBILIZACAO)
                    ->whereNotNull('scheduled_at')
                    ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
                    ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
                    ->with(['asset', 'fleetDriver', 'maintenanceOrder.client'])
                    ->orderBy('scheduled_at')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento'),
                Tables\Columns\TextColumn::make('maintenanceOrder.client.name')
                    ->label('Cliente')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Saída Prevista')
                    ->dateTime('d/m H:i'),
                Tables\Columns\TextColumn::make('fleetDriver.name')
                    ->label('Motorista')
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
