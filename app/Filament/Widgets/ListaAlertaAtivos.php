<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Asset;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class ListaAlertaAtivos extends BaseWidget
{
    // Define a largura do componente (o Filament respeitará isso se estiver no Dashboard)
    protected int | string | array $columnSpan = ['md' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Asset::query()
                    ->when(\App\Support\Tenancy::current(), function (Builder $query, $tenant) {
                        return $query->where('tenant_id', $tenant->id);
                    })
                    ->where('status', 'alerta')
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