<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ListaAlertaAtivos extends BaseWidget
{
    // Define a largura do componente (o Filament respeitará isso se estiver no Dashboard)
    protected int|string|array $columnSpan = ['md' => 2];

    /**
     * "alerta" nunca foi um valor real de Asset.status (nada no sistema o
     * atribui) -- a lista abaixo usa o status real "manutencao" (ativo
     * parado/em manutencao e' o sinal de alerta que ja existe de verdade).
     * O filtro de tenant fica por conta do global scope (BelongsToTenant) --
     * filtrar de novo aqui manualmente escondia os dados de super admin.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Asset::query()
                    ->where('status', 'manutencao')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ativo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ViewColumn::make('status_alerta')
                    ->label('Status com Alerta')
                    ->view('filament.tables.columns.alerta-visual'),
            ])
            ->paginated(5)
            // Configurações para manter a estrutura visível mesmo sem dados
            ->emptyStateHeading('Sem alertas pendentes')
            ->emptyStateDescription('Tudo operando dentro da normalidade.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * IMPORTANTE: Isso força o Filament a não ocultar a tabela
     * quando ela não encontrar registros, mantendo o layout fixo.
     */
    protected function isTableEmpty(): bool
    {
        return false;
    }
}
