<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Support\Tenancy;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopClientsByRentals extends BaseWidget
{
    private const LIMITE_GENERICO = 3;

    private const LIMITE_EVENTOS = 5;

    /**
     * Usado em 2 lugares (App\Support\SegmentDashboardWidgets): segmento
     * Eventos (grid de 2 colunas, span 2 = linha inteira pra ele sozinho,
     * comportamento de propósito, top 5 cabe bem na largura toda) e
     * segmento genérico/default (grid de 3 colunas, span 1, top 3 pra
     * caber melhor numa coluna de 1/3 -- pedido explícito do usuário).
     */
    private function isSegmentoGenerico(): bool
    {
        return ! in_array(Tenancy::current()?->segment, [
            Client::NICHE_EVENTOS,
            Client::NICHE_CONSTRUCAO_CIVIL,
            Client::NICHE_INDUSTRIAL_HOSPITALAR,
        ], true);
    }

    /**
     * Span 1 só no segmento genérico, span 2 preservado no resto -- evita
     * mudar o layout do segmento Eventos, que não foi pedido. O motivo de
     * precisar ajustar isso pelo código: o wrapper interno de TODO widget
     * Filament (vendor/filament/widgets/resources/views/components/
     * widget.blade.php) já embrulha o próprio conteúdo num
     * <x-filament::grid.column>, que gera uma classe md:col-span-N REAL --
     * funciona mesmo dentro do grid CSS hand-rolled deste dashboard
     * (@livewire() direto, não usa o componente de grid do Filament).
     */
    public function getColumnSpan(): int|string|array
    {
        return $this->isSegmentoGenerico() ? 1 : ['md' => 2];
    }

    public function table(Table $table): Table
    {
        $limite = $this->isSegmentoGenerico() ? self::LIMITE_GENERICO : self::LIMITE_EVENTOS;

        return $table
            ->heading("Top {$limite} Clientes com Mais Locações")
            ->query(
                Client::withCount('contracts')
                    ->orderByDesc('contracts_count')
                    ->limit($limite)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente'),
                Tables\Columns\TextColumn::make('contracts_count')
                    ->label('Contratos')
                    ->badge()
                    ->color('primary')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sem contratos registrados')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
