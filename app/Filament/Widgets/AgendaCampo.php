<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AgendaCampo extends BaseWidget
{
    protected int|string|array $columnSpan = ['md' => 1];

    // Impede que o widget tente renderizar se não houver um tenant ativo
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    /**
     * "agendado" nunca foi um valor real de MaintenanceOrder.status (nada no
     * sistema o atribui) -- scheduled_at em si e' real (a pagina Agenda
     * Tecnico/Programacao usa de verdade). A regra vira: OS ainda ativa
     * (nao concluida/cancelada) com scheduled_at nas proximas 24h. O filtro
     * de tenant fica por conta do global scope (BelongsToTenant) -- filtrar
     * de novo aqui manualmente escondia os dados de super admin. 'titulo'
     * tambem nao existe no model -- a coluna real e' 'description'.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                MaintenanceOrder::query()
                    ->whereNotIn('status', ['Concluída', 'Cancelada'])
                    ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Serviço')
                    ->limit(30),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Previsão')
                    ->dateTime('d/m H:i'),
                Tables\Columns\TextColumn::make('technician.name')
                    ->label('Técnico'),
            ])
            ->paginated(false);
    }
}
